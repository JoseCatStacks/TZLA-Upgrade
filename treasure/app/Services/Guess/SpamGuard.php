<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;
use Illuminate\Support\Facades\RateLimiter;

final class SpamGuard
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly int $maxPerWindow = 10,
    ) {}

    /**
     * The ceiling must stay above the maximum attempts a wallet can earn per
     * word, otherwise large NFT holders are sold attempts they cannot spend.
     */
    public function maxPerWindow(): int
    {
        return max(1, $this->maxPerWindow);
    }

    /**
     * Returns true if the wallet is within the allowed rate.
     * Must be called BEFORE recording the attempt so the check is accurate.
     */
    public function isAllowed(Wallet $wallet): bool
    {
        return ! RateLimiter::tooManyAttempts($this->key($wallet), $this->maxPerWindow());
    }

    /**
     * Records one guess attempt for the wallet. Call this only after isAllowed() is true
     * and the guess has been accepted.
     */
    public function record(Wallet $wallet): void
    {
        RateLimiter::hit($this->key($wallet), self::WINDOW_SECONDS);
    }

    /** Seconds remaining until the current window resets. */
    public function retryAfter(Wallet $wallet): int
    {
        return RateLimiter::availableIn($this->key($wallet));
    }

    private function key(Wallet $wallet): string
    {
        return 'guess-rate:'.$wallet->id;
    }
}
