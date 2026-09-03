<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StakeRecord;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Computes projected weekly / monthly staking rewards for a wallet: what its
 * open positions will earn over the next 7 / 30 days at their tier rates.
 *
 * Everything is derived from the verified `stake_records` table plus the shared
 * tier table in config/staking.php — no Helius / RPC call is made on this path.
 * The USD figure reuses the background-warmed Redis price oracle, so a read
 * never fans out to Jupiter either. Assembled summaries are themselves cached
 * per wallet on the oracle Redis store for `staking.rewards_cache_ttl` seconds.
 */
final class RewardService
{
    private const WEEK_DAYS  = 7;
    private const MONTH_DAYS = 30;

    public function __construct(private readonly JupiterPriceService $prices)
    {
    }

    /**
     * Projected rewards over the next 7 days across the wallet's open
     * positions, in base units.
     */
    public function weeklyRewardsRaw(string $wallet): string
    {
        return $this->projectedRewardsRaw($wallet, self::WEEK_DAYS);
    }

    /** Projected rewards over the next 30 days for the wallet, in base units. */
    public function monthlyRewardsRaw(string $wallet): string
    {
        return $this->projectedRewardsRaw($wallet, self::MONTH_DAYS);
    }

    /**
     * Full reward summary for a wallet, ready to return as JSON. Cached briefly
     * on Redis so repeated reads for the same wallet stay off the database.
     *
     * @return array{
     *     wallet: string, as_of: int, price_usd: float|null,
     *     total_staked_raw: string, open_positions: int,
     *     weekly: array, monthly: array, positions: list<array>
     * }
     */
    public function summary(string $wallet): array
    {
        $ttl = (int) config('staking.rewards_cache_ttl', 15);

        return $this->cache()->remember(
            "staking:rewards:{$wallet}",
            $ttl,
            fn (): array => $this->buildSummary($wallet, now()),
        );
    }

    private function buildSummary(string $wallet, Carbon $asOf): array
    {
        // Only open positions earn going forward, so closed rows never
        // contribute to a projection.
        $records = StakeRecord::openForWallet($wallet)
            ->orderByDesc('staked_at')
            ->get();

        $weeklyRaw  = '0';
        $monthlyRaw = '0';
        $stakedRaw  = '0';
        $open       = 0;
        $positions  = [];

        foreach ($records as $record) {
            $w = $record->projectedRewardRaw(self::WEEK_DAYS);
            $m = $record->projectedRewardRaw(self::MONTH_DAYS);

            $weeklyRaw  = bcadd($weeklyRaw, $w);
            $monthlyRaw = bcadd($monthlyRaw, $m);

            $stakedRaw = bcadd($stakedRaw, $record->amount_raw);
            $open++;

            $positions[] = [
                'amount_raw'       => $record->amount_raw,
                'nft_tier'         => $record->nft_tier,
                'daily_rate_pct'   => $this->dailyRatePct($record->nft_tier),
                'staked_at'        => $record->staked_at->toIso8601String(),
                'unstaked_at'      => $record->unstaked_at?->toIso8601String(),
                'weekly_raw'       => $w, // projected next-7-day reward
                'monthly_raw'      => $m, // projected next-30-day reward
            ];
        }

        $price = $this->prices->price();

        return [
            'wallet'           => $wallet,
            'as_of'            => $asOf->timestamp,
            'price_usd'        => $price,
            'total_staked_raw' => $stakedRaw,
            'open_positions'   => $open,
            'weekly'           => $this->period(7, $weeklyRaw, $price),
            'monthly'          => $this->period(30, $monthlyRaw, $price),
            'positions'        => $positions,
        ];
    }

    /** Sum the projected reward across the wallet's open positions. */
    private function projectedRewardsRaw(string $wallet, int $days): string
    {
        $total = '0';

        foreach (StakeRecord::openForWallet($wallet)->get() as $record) {
            $total = bcadd($total, $record->projectedRewardRaw($days));
        }

        return $total;
    }

    /** Shape a single period block: raw, tokens, and USD (when a price is known). */
    private function period(int $days, string $raw, ?float $price): array
    {
        $tokens = (float) bcdiv($raw, config('staking.token_base_units'), 9);

        return [
            'window_days'  => $days,
            'reward_raw'   => $raw,
            'reward_tzla'  => $tokens,
            'reward_usd'   => $price !== null ? round($tokens * $price, 2) : null,
        ];
    }

    /** Human-readable daily rate for a tier, e.g. 0.369. */
    private function dailyRatePct(int $tier): float
    {
        $rates = config('staking.rate_numerators');
        $num   = (float) ($rates[$tier] ?? $rates[0]);

        return round($num / (float) config('staking.rate_denominator') * 100, 3);
    }

    private function cache(): Repository
    {
        return Cache::store(config('oracle.store', 'redis'));
    }
}
