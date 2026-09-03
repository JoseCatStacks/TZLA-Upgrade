<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\JupiterPriceService;
use App\Services\SolanaOracleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Refreshes the global staking accounts (pool + reward vault) and the TZLA USD
 * price into Redis so the pool-stats panel is served from cache regardless of
 * site traffic. Scheduled every few seconds in routes/console.php.
 */
final class RefreshPoolOracle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The scheduler re-dispatches on a fixed cadence, so a failed run is simply
    // retried on the next tick — no point piling up retries of stale work.
    public int $tries = 1;

    public int $timeout = 20;

    public function handle(SolanaOracleService $oracle, JupiterPriceService $price): void
    {
        foreach (array_keys((array) config('oracle.global', [])) as $name) {
            $oracle->refreshGlobal($name);
        }

        // Refresh the TZLA price and advance the reward-vault funding water mark
        // off the freshly-warmed balance, so both the distributed figure and its
        // USD value are ready in Redis before any visitor asks.
        $price->refresh();

        if (($balance = $oracle->rewardVaultBalanceRaw()) !== null) {
            $oracle->trackFundedHighWater($balance);
        }
    }
}
