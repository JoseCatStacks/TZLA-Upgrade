<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;

final class AttemptPolicy
{
    public function attemptsAllowedPerWeek(Wallet $wallet): int
    {
        if (! $wallet->canPlay()) {
            return 0;
        }

        $base = (int) config('game.attempts.base_per_week', config('game.attempts.base_per_word', 1));
        $max  = (int) config('game.attempts.max_per_week', config('game.attempts.max_per_word', 5));

        // Uncapped, a wallet holding hundreds of NFTs could brute-force every
        // answer regardless of the per-bundle fee.
        return max(1, min($base + $wallet->nftCount(), $max));
    }

    /** @deprecated use attemptsAllowedPerWeek */
    public function attemptsAllowedPerWord(Wallet $wallet): int
    {
        return $this->attemptsAllowedPerWeek($wallet);
    }
}
