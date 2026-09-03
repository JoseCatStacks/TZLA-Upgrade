<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;
use App\Models\Week;

final class AttemptPolicy
{
    public function hasUnlimitedAttempts(Week $week): bool
    {
        $weeks = config('game.weeks.unlimited_attempts', [1]);

        if (! is_array($weeks)) {
            return false;
        }

        return in_array((int) $week->number, array_map('intval', $weeks), true);
    }

    public function attemptsAllowedPerWeek(Wallet $wallet, ?Week $week = null): int
    {
        if (! $wallet->canPlay()) {
            return 0;
        }

        if ($week !== null && $this->hasUnlimitedAttempts($week)) {
            // Sentinel for "no cap". Controllers expose unlimited_attempts instead
            // of showing this number to players.
            return PHP_INT_MAX;
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
