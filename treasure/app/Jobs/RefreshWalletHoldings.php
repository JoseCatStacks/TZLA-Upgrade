<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\Solana\WalletHoldingsSync;
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

    public function handle(WalletHoldingsSync $sync): void
    {
        $sync->refresh($this->wallet);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RefreshWalletHoldings failed', [
            'wallet' => $this->wallet->address,
            'error' => $e->getMessage(),
        ]);
    }
}
