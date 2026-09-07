<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;
use App\Models\Week;
use Illuminate\Support\Facades\Log;

final class WinnerLogger
{
    public function record(Wallet $wallet, Week $week, int $place = 0, ?float $prizeXmr = null): void
    {
        $channel = (string) config('game.winners.log_channel', 'winners');

        Log::channel($channel)->info('week_completed', [
            'week_id' => $week->id,
            'week_number' => $week->number,
            'place' => $place,
            'prize_xmr' => $prizeXmr,
            'username' => $wallet->username,
            'wallet_address' => $wallet->address,
            'payout_address' => $wallet->payout_address,
            'completed_at' => now()->toIso8601String(),
        ]);
    }
}
