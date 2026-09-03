<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StakeRecord extends Model
{
    protected $fillable = [
        'wallet', 'amount_raw', 'nft_tier',
        'staked_at', 'unstaked_at', 'stake_tx', 'unstake_tx',
    ];

    protected $casts = [
        'staked_at'   => 'datetime',
        'unstaked_at' => 'datetime',
    ];

    /** Open positions for a wallet (not yet unstaked). */
    public function scopeOpenForWallet(Builder $query, string $wallet): Builder
    {
        return $query->where('wallet', $wallet)->whereNull('unstaked_at');
    }

    /** Daily-rate numerator for this row's tier (denominator = rate_denominator). */
    private function rateNumerator(): string
    {
        $rates = config('staking.rate_numerators');

        return (string) ($rates[$this->nft_tier] ?? $rates[0]);
    }

    /**
     * Rewards accrued by this position over the trailing window
     * [asOf − windowSeconds, asOf], in base units. Clamps to the position's own
     * active interval [staked_at, unstaked_at ?? asOf] so a closed position only
     * earns for the slice it was actually staked, and a position opened mid-window
     * only earns from staked_at. Pass windowSeconds = null for lifetime accrual.
     */
    public function rewardForWindowRaw(?int $windowSeconds = null, ?\DateTimeInterface $asOf = null): string
    {
        $asOfTs = ($asOf ?? now())->getTimestamp();

        // Active interval of this position.
        $activeStart = $this->staked_at->getTimestamp();
        $activeEnd   = $this->unstaked_at?->getTimestamp() ?? $asOfTs;

        // Trailing window (or unbounded for lifetime).
        $windowStart = $windowSeconds === null ? $activeStart : $asOfTs - $windowSeconds;
        $windowEnd   = $asOfTs;

        // Overlap of the two intervals.
        $start   = max($activeStart, $windowStart);
        $end     = min($activeEnd, $windowEnd);
        $elapsed = $end - $start;
        if ($elapsed <= 0) {
            return '0';
        }

        // reward_raw = amount_raw * rate_num * elapsed / (denom * seconds_per_day)
        $num   = bcmul(bcmul($this->amount_raw, $this->rateNumerator()), (string) $elapsed);
        $denom = bcmul(config('staking.rate_denominator'), config('staking.seconds_per_day'));

        return bcdiv($num, $denom, 0);
    }

    /**
     * Projected rewards this position will earn over the next $days at its
     * tier rate, in base units. A closed position earns nothing going forward.
     */
    public function projectedRewardRaw(int $days): string
    {
        if ($this->unstaked_at !== null) {
            return '0';
        }

        $num = bcmul(bcmul($this->amount_raw, $this->rateNumerator()), (string) $days);

        return bcdiv($num, config('staking.rate_denominator'), 0);
    }

    /** As rewardForWindowRaw, expressed in whole TZLA (9 dp). */
    public function rewardForWindowTokens(?int $windowSeconds = null, ?\DateTimeInterface $asOf = null): string
    {
        return bcdiv($this->rewardForWindowRaw($windowSeconds, $asOf), config('staking.token_base_units'), 9);
    }

    /**
     * Lifetime rewards accrued since staked_at (base units). Retained for the
     * predicted-yield endpoint; delegates to the windowed calculator.
     */
    public function predictedYieldRaw(?\DateTimeInterface $asOf = null): string
    {
        if ($this->unstaked_at !== null) {
            return '0';
        }

        return $this->rewardForWindowRaw(null, $asOf);
    }

    public function predictedYieldTokens(?\DateTimeInterface $asOf = null): string
    {
        return bcdiv($this->predictedYieldRaw($asOf), config('staking.token_base_units'), 9);
    }
}
