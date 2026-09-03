<?php

declare(strict_types=1);

namespace App\Services\Guess;

final readonly class GuessResult
{
    public function __construct(
        public bool $isCorrect,
        public bool $wasAlreadySolved,
        public int $attemptsUsed,
        public int $attemptsAllowed,
        public bool $weekComplete,
        public int $solvedCount = 0,
        public int $totalWords = 0,
    ) {}

    public function attemptsLeft(): int
    {
        return max(0, $this->attemptsAllowed - $this->attemptsUsed);
    }
}
