<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\Solana\HoldingsVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RefreshWalletHoldings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly Wallet $wallet) {}

    public function handle(HoldingsVerifier $verifier): void
    {
        $holdings = $verifier->holdings($this->wallet->address);

        $this->wallet->forceFill([
            'tzla_balance_cached'        => $holdings->tzlaBalance,
            'staked_amount_cached'       => $holdings->stakedAmount,
            'nft_count_cached'           => $holdings->nftCount,
            'golden_ticket_count_cached' => $holdings->goldenTicketCount,
            'holdings_refreshed_at'      => now(),
        ])->save();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RefreshWalletHoldings failed', [
            'wallet' => $this->wallet->address,
            'error' => $e->getMessage(),
        ]);
    }
}
