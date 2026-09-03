<?php

declare(strict_types=1);

namespace App\Services\Solana;

use App\Models\FeePayment;
use App\Models\Wallet;
use App\Models\Word;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Tracks which on-chain fee transactions have already been spent.
 *
 * Without this, a player could pay once and replay the same transaction
 * signature on every subsequent guess.
 */
final class FeeLedger
{
    public function alreadySpent(string $signature): bool
    {
        return FeePayment::query()->where('signature', $signature)->exists();
    }

    /**
     * Atomically claim a signature for this guess.
     *
     * Returns false when the signature was already spent. The unique index on
     * `signature` is what makes this safe against two concurrent requests
     * submitting the same transaction.
     */
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
