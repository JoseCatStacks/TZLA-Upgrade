<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Jobs\SendTelegramMessage;
use App\Models\Guess;
use App\Models\Wallet;
use App\Models\WeekCompletion;
use App\Models\Word;
use App\Models\WordCompletion;
use Illuminate\Support\Facades\DB;

final class GuessService
{
    public function __construct(
        private readonly GuessNormalizer $normalizer,
        private readonly AttemptPolicy $policy,
        private readonly WinnerLogger $winnerLogger,
    ) {}

    public function attemptsUsedForWord(Wallet $wallet, Word $word): int
    {
        return Guess::query()
            ->where('wallet_id', $wallet->id)
            ->where('word_id', $word->id)
            ->count();
    }

    public function hasSolved(Wallet $wallet, Word $word): bool
    {
        return WordCompletion::query()
            ->where('wallet_id', $wallet->id)
            ->where('word_id', $word->id)
            ->exists();
    }

    /**
     * Solved word ids for a whole week in one query, for listing endpoints that
     * would otherwise issue a query per word.
     *
     * @param  list<int>  $wordIds
     * @return array<int, true>
     */
    public function solvedWordIds(Wallet $wallet, array $wordIds): array
    {
        if ($wordIds === []) {
            return [];
        }

        return WordCompletion::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('word_id', $wordIds)
            ->pluck('word_id')
            ->flip()
            ->map(static fn (): bool => true)
            ->all();
    }

    /**
     * Attempt counts keyed by word id, in one query.
     *
     * @param  list<int>  $wordIds
     * @return array<int, int>
     */
    public function attemptCountsByWord(Wallet $wallet, array $wordIds): array
    {
        if ($wordIds === []) {
            return [];
        }

        return Guess::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('word_id', $wordIds)
            ->selectRaw('word_id, COUNT(*) as attempts')
            ->groupBy('word_id')
            ->pluck('attempts', 'word_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    public function submit(Wallet $wallet, Word $word, string $rawGuess): GuessResult
    {
        $attemptsAllowed = $this->policy->attemptsAllowedPerWord($wallet);
        $totalWords = Word::query()->where('week_id', $word->week_id)->count();

        if ($this->hasSolved($wallet, $word)) {
            return new GuessResult(
                isCorrect: true,
                wasAlreadySolved: true,
                attemptsUsed: $this->attemptsUsedForWord($wallet, $word),
                attemptsAllowed: $attemptsAllowed,
                weekComplete: $this->isWeekComplete($wallet, $word->week_id),
                solvedCount: $this->solvedCount($wallet, $word->week_id),
                totalWords: $totalWords,
            );
        }

        $attemptsUsed = $this->attemptsUsedForWord($wallet, $word);
        if ($attemptsUsed >= $attemptsAllowed) {
            return new GuessResult(
                isCorrect: false,
                wasAlreadySolved: false,
                attemptsUsed: $attemptsUsed,
                attemptsAllowed: $attemptsAllowed,
                weekComplete: $this->isWeekComplete($wallet, $word->week_id),
                solvedCount: $this->solvedCount($wallet, $word->week_id),
                totalWords: $totalWords,
            );
        }

        $normalized = $this->normalizer->normalize($rawGuess);
        $isCorrect = $normalized !== '' && $normalized === $word->answer_normalized;

        [$guess, $weekComplete] = DB::transaction(function () use ($wallet, $word, $rawGuess, $normalized, $isCorrect): array {
            $guess = Guess::create([
                'wallet_id' => $wallet->id,
                'word_id' => $word->id,
                'guess_raw' => mb_substr($rawGuess, 0, 255),
                'guess_normalized' => mb_substr($normalized, 0, 255),
                'is_correct' => $isCorrect,
                'created_at' => now(),
            ]);

            $weekComplete = false;

            if ($isCorrect) {
                // firstOrCreate rather than create: two correct guesses racing on
                // the same word would otherwise collide on the unique index and
                // surface as a 500 after the player has already paid.
                WordCompletion::firstOrCreate(
                    ['wallet_id' => $wallet->id, 'word_id' => $word->id],
                    ['correct_guess_id' => $guess->id, 'completed_at' => now()],
                );

                if ($this->isWeekComplete($wallet, $word->week_id)) {
                    WeekCompletion::firstOrCreate(
                        ['wallet_id' => $wallet->id, 'week_id' => $word->week_id],
                        ['completed_at' => now()],
                    );
                    $weekComplete = true;
                }
            }

            return [$guess, $weekComplete];
        });

        if ($isCorrect) {
            SendTelegramMessage::dispatch(sprintf(
                '[TZLA] Wallet %s solved word %d of week %d.',
                $wallet->shortAddress(),
                $word->position,
                $word->week?->number ?? $word->week_id,
            ));

            if ($weekComplete) {
                if ($word->week !== null) {
                    $this->winnerLogger->record($wallet, $word->week);
                }

                SendTelegramMessage::dispatch(sprintf(
                    '[TZLA] 🏴‍☠️ Wallet %s COMPLETED week %d — reward payout ready.',
                    $wallet->shortAddress(),
                    $word->week?->number ?? $word->week_id,
                ));
            }
        }

        return new GuessResult(
            isCorrect: $isCorrect,
            wasAlreadySolved: false,
            attemptsUsed: $attemptsUsed + 1,
            attemptsAllowed: $attemptsAllowed,
            weekComplete: $weekComplete,
            solvedCount: $this->solvedCount($wallet, $word->week_id),
            totalWords: $totalWords,
        );
    }

    private function solvedCount(Wallet $wallet, int $weekId): int
    {
        return WordCompletion::query()
            ->where('wallet_id', $wallet->id)
            ->whereIn('word_id', Word::query()->where('week_id', $weekId)->select('id'))
            ->count();
    }

    private function isWeekComplete(Wallet $wallet, int $weekId): bool
    {
        $totalWords = Word::query()->where('week_id', $weekId)->count();
        if ($totalWords === 0) {
            return false;
        }

        return $this->solvedCount($wallet, $weekId) >= $totalWords;
    }
}
