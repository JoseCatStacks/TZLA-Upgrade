<?php

declare(strict_types=1);

namespace App\Services\Solana;

use App\Models\FeePayment;
use App\Models\Wallet;
use App\Models\Week;
use App\Models\Word;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Tracks which on-chain fee transactions have already been spent.
 */
final class FeeLedger
{
    public function alreadySpent(string $signature): bool
    {
        return FeePayment::query()->where('signature', $signature)->exists();
    }

    public function claimForWeek(string $signature, Wallet $wallet, Week $week, float $amountSol): bool
    {
        try {
            FeePayment::create([
                'signature'  => $signature,
                'wallet_id'  => $wallet->id,
                'week_id'    => $week->id,
                'word_id'    => null,
                'amount_sol' => $amountSol,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /** @deprecated per-word guesses; prefer claimForWeek */
    public function claim(string $signature, Wallet $wallet, Word $word, float $amountSol): bool
    {
        try {
            FeePayment::create([
                'signature'  => $signature,
                'wallet_id'  => $wallet->id,
                'word_id'    => $word->id,
                'amount_sol' => $amountSol,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
