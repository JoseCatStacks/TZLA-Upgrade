<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Jobs\SendTelegramMessage;
use App\Models\BundleAttempt;
use App\Models\Guess;
use App\Models\Wallet;
use App\Models\Week;
use App\Models\WeekCompletion;
use App\Models\WordCompletion;
use Illuminate\Support\Facades\DB;

final class BundleGuessService
{
    public function __construct(
        private readonly GuessNormalizer $normalizer,
        private readonly AttemptPolicy $policy,
        private readonly WinnerLogger $winnerLogger,
    ) {}

    public function attemptsUsed(Wallet $wallet, Week $week): int
    {
        return BundleAttempt::query()
            ->where('wallet_id', $wallet->id)
            ->where('week_id', $week->id)
            ->count();
    }

    public function hasCompleted(Wallet $wallet, Week $week): bool
    {
        return WeekCompletion::query()
            ->where('wallet_id', $wallet->id)
            ->where('week_id', $week->id)
            ->exists();
    }

    /**
     * Score a full set of answers. Never returns which positions matched —
     * only the count. On a full clear, records completions + winner log.
     *
     * @param  array<int, string>  $answersByPosition  position => raw answer
     * @return array{
     *   correct_count: int,
     *   total_words: int,
     *   is_complete: bool,
     *   attempts_used: int,
     *   attempts_allowed: int,
     *   attempts_left: int
     * }
     */
    public function submit(Wallet $wallet, Week $week, array $answersByPosition, ?string $feeSignature): array
    {
        $words = $week->words()->orderBy('position')->get();
        $total = $words->count();
        $attemptsAllowed = $this->policy->attemptsAllowedPerWeek($wallet);
        $attemptsUsed = $this->attemptsUsed($wallet, $week);

        $correct = 0;
        $normalizedAnswers = [];
        foreach ($words as $word) {
            $raw = (string) ($answersByPosition[(int) $word->position] ?? '');
            $normalized = $this->normalizer->normalize($raw);
            $normalizedAnswers[(int) $word->position] = $normalized;
            if ($normalized !== '' && $normalized === $word->answer_normalized) {
                $correct++;
            }
        }

        $isComplete = $total > 0 && $correct === $total;

        DB::transaction(function () use (
            $wallet, $week, $words, $correct, $total, $isComplete, $normalizedAnswers, $feeSignature
        ): void {
            BundleAttempt::create([
                'wallet_id' => $wallet->id,
                'week_id' => $week->id,
                'correct_count' => $correct,
                'total_words' => $total,
                'is_complete' => $isComplete,
                'answers' => $normalizedAnswers,
                'fee_signature' => $feeSignature,
                'created_at' => now(),
            ]);

            if (! $isComplete) {
                return;
            }

            foreach ($words as $word) {
                $normalized = $normalizedAnswers[(int) $word->position] ?? '';
                $guess = Guess::create([
                    'wallet_id' => $wallet->id,
                    'word_id' => $word->id,
                    'guess_raw' => mb_substr($normalized, 0, 255),
                    'guess_normalized' => mb_substr($normalized, 0, 255),
                    'is_correct' => true,
                    'created_at' => now(),
                ]);

                WordCompletion::firstOrCreate(
                    ['wallet_id' => $wallet->id, 'word_id' => $word->id],
                    ['correct_guess_id' => $guess->id, 'completed_at' => now()],
                );
            }

            WeekCompletion::firstOrCreate(
                ['wallet_id' => $wallet->id, 'week_id' => $week->id],
                ['completed_at' => now()],
            );
        });

        if ($isComplete) {
            $this->winnerLogger->record($wallet, $week);

            SendTelegramMessage::dispatch(sprintf(
                '[TZLA] 🏴‍☠️ Wallet %s COMPLETED week %d — username=%s payout=%s',
                $wallet->shortAddress(),
                $week->number,
                $wallet->username ?: '—',
                $wallet->payout_address ?: $wallet->address,
            ));
        }

        return [
            'correct_count' => $correct,
            'total_words' => $total,
            'is_complete' => $isComplete,
            'attempts_used' => $attemptsUsed + 1,
            'attempts_allowed' => $attemptsAllowed,
            'attempts_left' => max(0, $attemptsAllowed - ($attemptsUsed + 1)),
        ];
    }
}
