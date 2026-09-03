<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Redis-backed USD price oracle for TZLA, fronting the Jupiter Price API.
 *
 * The price is refreshed in the background by RefreshPoolOracle on the same
 * cadence as the on-chain accounts, so a visitor's read is always served from
 * Redis — Jupiter is hit at most once per refresh interval regardless of site
 * traffic. Last-known-good is served on a fetch failure, and a per-key lock
 * guards against stampedes on a cold cache.
 */
final class JupiterPriceService
{
    /**
     * Current TZLA price in USD, served from Redis. Falls back to a single
     * guarded live fetch only on a cold cache; returns null when the price is
     * genuinely unavailable (so callers can hide the USD figure rather than
     * show a wrong one).
     */
    public function price(): ?float
    {
        $entry = $this->cache()->get($this->key());

        if ($entry === null) {
            $entry = $this->refresh();
        }

        $usd = $entry['usd'] ?? null;

        return is_numeric($usd) ? (float) $usd : null;
    }

    /**
     * Fetch the latest price into Redis. Called by the scheduler and lazily on
     * a cold cache. Serves last-known-good on failure. Returns the cached entry
     * (['usd' => float|null, 'fetched_at' => int]).
     */
    public function refresh(): array
    {
        $key  = $this->key();
        $lock = $this->cache()->lock("oracle:lock:{$key}", (int) config('oracle.lock_seconds', 10));

        // If another worker is already refreshing, serve whatever is cached
        // rather than queue behind it; only fetch unlocked as a cold-start
        // fallback when nothing is cached yet.
        if (! $lock->get()) {
            return $this->cache()->get($key) ?? $this->fetchEntry($key);
        }

        try {
            return $this->fetchEntry($key);
        } finally {
            $lock->release();
        }
    }

    private function fetchEntry(string $key): array
    {
        try {
            $usd = $this->fetchPrice();

            $entry = ['usd' => $usd, 'fetched_at' => now()->timestamp];
            // Stored without expiry — last-known-good until the next refresh.
            $this->cache()->forever($key, $entry);

            Log::info('oracle: refreshed TZLA price', ['usd' => $usd]);

            return $entry;
        } catch (\Throwable $e) {
            report($e);

            $stale = $this->cache()->get($key);

            if ($stale !== null) {
                Log::warning('oracle: serving stale TZLA price after fetch failure', [
                    'age_s' => now()->timestamp - (int) ($stale['fetched_at'] ?? 0),
                    'error' => $e->getMessage(),
                ]);

                return $stale;
            }

            Log::error('oracle: TZLA price fetch failed with no cached fallback', [
                'error' => $e->getMessage(),
            ]);

            return ['usd' => null, 'fetched_at' => 0];
        }
    }

    /**
     * Single call to the Jupiter Price API. Tolerates both the v3 shape
     * ({ "<mint>": { "usdPrice": n } }) and the legacy v6 shape
     * ({ "data": { "<mint>": { "price": "n" } } }). Throws on a missing price
     * so the caller can fall back to cache.
     */
    private function fetchPrice(): float
    {
        $mint = (string) config('oracle.price.mint');

        $response = Http::timeout(10)
            ->acceptJson()
            ->get((string) config('oracle.price.endpoint'), ['ids' => $mint]);

        $json = $response->json();

        if (! is_array($json)) {
            throw new \RuntimeException('Jupiter price: non-JSON response');
        }

        // v6 nests the map under "data"; v3 returns it at the top level.
        $map   = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $entry = $map[$mint] ?? null;

        $price = is_array($entry) ? ($entry['usdPrice'] ?? $entry['price'] ?? null) : null;

        if (! is_numeric($price)) {
            throw new \RuntimeException("Jupiter price: no usable price for {$mint}");
        }

        return (float) $price;
    }

    private function cache(): Repository
    {
        return Cache::store(config('oracle.store', 'redis'));
    }

    private function key(): string
    {
        return 'oracle:price:tzla';
    }
}
