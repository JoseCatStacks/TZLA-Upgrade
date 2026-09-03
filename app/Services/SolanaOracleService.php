<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Redis-backed read oracle for the staking program.
 *
 * Fronts Helius so that high-volume, abuse-prone reads are served from cache
 * instead of hitting the RPC once per request:
 *
 *  - Global accounts (pool, reward vault) are refreshed in the background by
 *    RefreshPoolOracle and served to every visitor straight from Redis.
 *  - getAssetsByOwner is cached per-owner with a short TTL (negative results
 *    included) and guarded by a per-key lock against stampedes.
 *
 * Responses are cached and replayed verbatim as raw JSON-RPC `result` payloads,
 * so the browser keeps decoding them with Anchor exactly as before.
 */
final class SolanaOracleService
{
    private readonly string $endpoint;

    public function __construct()
    {
        $apiKey = (string) config('services.helius.api_key', '');
        $this->endpoint = $apiKey !== ''
            ? "https://mainnet.helius-rpc.com/?api-key={$apiKey}"
            : '';
    }

    /**
     * Classify a JSON-RPC request. Returns a cache "kind" the oracle can serve
     * ('global:pool', 'global:reward_vault', 'assets'), or null when the request
     * should be forwarded to Helius live (everything else: tx path, per-wallet
     * account reads, malformed params).
     */
    public function kindFor(?string $method, mixed $params): ?string
    {
        if ($method === 'getAccountInfo'
            && $this->accountAddress($params) === config('oracle.global.pool.address')) {
            return 'global:pool';
        }

        if ($method === 'getTokenAccountBalance'
            && $this->accountAddress($params) === config('oracle.global.reward_vault.address')) {
            return 'global:reward_vault';
        }

        if ($method === 'getAssetsByOwner' && $this->isValidPubkey($this->ownerAddress($params))) {
            return 'assets';
        }

        return null;
    }

    /**
     * Resolve a previously-classified request to its raw JSON-RPC `result`.
     */
    public function resultFor(string $kind, mixed $params): mixed
    {
        return match ($kind) {
            'global:pool'         => $this->global('pool')['result'] ?? null,
            'global:reward_vault' => $this->global('reward_vault')['result'] ?? null,
            'assets'              => $this->assets($params),
            default               => null,
        };
    }

    /**
     * Refresh one global account into Redis. Called by the scheduler and lazily
     * on a cold cache. Serves last-known-good on failure. Returns the entry
     * (['result' => mixed, 'fetched_at' => int]).
     */
    public function refreshGlobal(string $name): array
    {
        $key  = $this->globalKey($name);
        $lock = $this->cache()->lock("oracle:lock:{$key}", (int) config('oracle.lock_seconds', 10));

        // If another worker holds the lock, serve whatever is already cached
        // rather than queueing behind it. Only fetch unlocked as a cold-start
        // fallback when nothing is cached yet.
        if (! $lock->get()) {
            return $this->cache()->get($key) ?? $this->fetchGlobalEntry($name, $key);
        }

        try {
            return $this->fetchGlobalEntry($name, $key);
        } finally {
            $lock->release();
        }
    }

    /**
     * Whether the cached global entry is older than the staleness threshold.
     */
    public function isGlobalStale(string $name): bool
    {
        $entry = $this->cache()->get($this->globalKey($name));
        if ($entry === null) {
            return true;
        }

        return (now()->timestamp - (int) ($entry['fetched_at'] ?? 0))
            > (int) config('oracle.staleness_threshold_seconds', 120);
    }

    /**
     * Current reward-vault balance in base units, read from the background-warmed
     * cache (getTokenAccountBalance result). Returns null when unavailable so
     * callers can degrade rather than report a wrong figure.
     */
    public function rewardVaultBalanceRaw(): ?string
    {
        $result = $this->global('reward_vault')['result'] ?? null;
        $amount = is_array($result) ? ($result['value']['amount'] ?? null) : null;

        return is_numeric($amount) ? (string) $amount : null;
    }

    /**
     * Cumulative TZLA distributed as rewards, in base units:
     *
     *     distributed = max(configured_funded, observed_high_water) − balance
     *
     * The high-water mark of the observed vault balance is tracked in Redis so
     * operator top-ups are captured without bookkeeping, and the result is
     * clamped at zero so the counter never runs negative. Returns '0' when the
     * vault balance is unavailable. Computed from already-cached data — costs no
     * RPC.
     */
    public function distributedRaw(): string
    {
        $balance = $this->rewardVaultBalanceRaw();
        if ($balance === null) {
            return '0';
        }

        $configured = (string) config('oracle.reward_vault_funded_raw', '0');
        $highWater  = $this->trackFundedHighWater($balance);
        $funded     = $this->maxRaw($configured, $highWater);

        $distributed = $this->subRaw($funded, $balance);

        return $this->maxRaw('0', $distributed);
    }

    /**
     * Record the highest reward-vault balance ever observed and return it. This
     * is the self-healing funding floor: a top-up spikes the balance and raises
     * the water mark, so distribution is measured against real inflow even if
     * the configured funded total is left at zero.
     */
    public function trackFundedHighWater(string $balance): string
    {
        $key  = 'oracle:reward_vault:high_water';
        $prev = (string) ($this->cache()->get($key) ?? '0');

        if ($this->cmpRaw($balance, $prev) > 0) {
            $this->cache()->forever($key, $balance);

            return $balance;
        }

        return $prev;
    }

    /**
     * Bump a coarse request counter (oracle:stat:{name}) so cache effectiveness
     * is observable without log spam. Best-effort: never let metrics break a read.
     */
    public function recordStat(string $name): void
    {
        try {
            $this->cache()->increment("oracle:stat:{$name}");
        } catch (\Throwable) {
            // metrics are non-critical
        }
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function global(string $name): array
    {
        return $this->cache()->get($this->globalKey($name)) ?? $this->refreshGlobal($name);
    }

    private function fetchGlobalEntry(string $name, string $key): array
    {
        $cfg = config("oracle.global.{$name}");

        try {
            $result = $this->call($cfg['method'], [$cfg['address'], $cfg['params'] ?? []]);
            $entry  = ['result' => $result, 'fetched_at' => now()->timestamp];
            // Stored without expiry — it is last-known-good until the next refresh.
            $this->cache()->forever($key, $entry);

            Log::info('oracle: refreshed global', [
                'name' => $name,
                'slot' => is_array($result) ? ($result['context']['slot'] ?? null) : null,
            ]);

            return $entry;
        } catch (\Throwable $e) {
            report($e);

            // Serve last-known-good if we have it; otherwise an empty result so
            // the client degrades gracefully instead of erroring. Either way the
            // degradation is logged so a Helius outage is never silent.
            $stale = $this->cache()->get($key);

            if ($stale !== null) {
                Log::warning('oracle: serving stale global after fetch failure', [
                    'name'  => $name,
                    'age_s' => now()->timestamp - (int) ($stale['fetched_at'] ?? 0),
                    'error' => $e->getMessage(),
                ]);

                return $stale;
            }

            Log::error('oracle: global fetch failed with no cached fallback', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);

            return ['result' => null, 'fetched_at' => 0];
        }
    }

    private function assets(mixed $params): mixed
    {
        $owner = $this->ownerAddress($params);
        $page  = (int) ($this->paramValue($params, 'page') ?? 1);
        $limit = (int) ($this->paramValue($params, 'limit') ?? 1000);

        $key = "oracle:assets:{$owner}:{$page}:{$limit}";

        $cached = $this->cache()->get($key);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $result = $this->call('getAssetsByOwner', [
                'ownerAddress' => $owner,
                'page'         => $page,
                'limit'        => $limit,
            ]);

            // Cache whatever comes back, including empty item lists, so spamming a
            // wallet with no NFTs still only costs one DAS call per TTL. Failures
            // are deliberately NOT cached (see catch) so we don't poison a wallet's
            // assets with an empty list during a transient Helius error.
            $this->cache()->put($key, $result, (int) config('oracle.ttl.assets', 30));

            return $result;
        } catch (\Throwable $e) {
            report($e);

            Log::warning('oracle: assets fetch failed, degrading to empty', [
                'owner' => $owner,
                'page'  => $page,
                'error' => $e->getMessage(),
            ]);

            // Degrade gracefully (mirrors the global path) instead of throwing an
            // uncaught exception up to the controller. Not cached, so the next
            // request retries.
            return ['total' => 0, 'limit' => $limit, 'page' => $page, 'items' => []];
        }
    }

    /**
     * Single JSON-RPC call to Helius. Returns the `result`; throws on RPC error
     * so callers can fall back to cache.
     */
    private function call(string $method, mixed $params): mixed
    {
        if ($this->endpoint === '') {
            throw new \RuntimeException('Helius API key is not configured');
        }

        $response = Http::timeout(15)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->endpoint, [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => $method,
                'params'  => $params,
            ]);

        $json = $response->json();

        if (! is_array($json) || isset($json['error'])) {
            throw new \RuntimeException(
                "Helius error for {$method}: " . json_encode($json['error'] ?? 'no response'),
            );
        }

        return $json['result'] ?? null;
    }

    private function cache(): Repository
    {
        return Cache::store(config('oracle.store', 'redis'));
    }

    private function globalKey(string $name): string
    {
        return "oracle:global:{$name}";
    }

    /**
     * Extract the account address (params[0]) from a getAccountInfo /
     * getTokenAccountBalance param array.
     */
    private function accountAddress(mixed $params): ?string
    {
        if (is_array($params) && isset($params[0]) && is_string($params[0])) {
            return $params[0];
        }

        return null;
    }

    /**
     * Extract ownerAddress from getAssetsByOwner params (object or positional).
     */
    private function ownerAddress(mixed $params): ?string
    {
        $owner = $this->paramValue($params, 'ownerAddress');

        return is_string($owner) ? $owner : null;
    }

    private function paramValue(mixed $params, string $key): mixed
    {
        if (is_array($params)) {
            if (array_key_exists($key, $params)) {
                return $params[$key];
            }
            if (isset($params[0]) && is_array($params[0]) && array_key_exists($key, $params[0])) {
                return $params[0][$key];
            }
        }

        return null;
    }

    // Base-unit amounts are u64 and can exceed PHP's int range once scaled by
    // 9 decimals, so they are handled as decimal strings. bcmath is used when
    // available (it is in the Sail image); the int fallback stays correct for
    // any value inside PHP_INT_MAX.

    private function subRaw(string $a, string $b): string
    {
        return function_exists('bcsub')
            ? bcsub($a, $b, 0)
            : (string) ((int) $a - (int) $b);
    }

    private function cmpRaw(string $a, string $b): int
    {
        return function_exists('bccomp')
            ? bccomp($a, $b, 0)
            : (int) $a <=> (int) $b;
    }

    private function maxRaw(string $a, string $b): string
    {
        return $this->cmpRaw($a, $b) >= 0 ? $a : $b;
    }

    private function isValidPubkey(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $value) === 1;
    }
}
