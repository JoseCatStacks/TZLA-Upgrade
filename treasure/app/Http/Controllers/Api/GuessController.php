<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramMessage;
use App\Models\FeePayment;
use App\Models\Wallet;
use App\Models\Week;
use App\Services\Guess\AttemptPolicy;
use App\Services\Guess\BundleGuessService;
use App\Services\Guess\FeeTier;
use App\Services\Guess\SpamGuard;
use App\Services\Solana\FeeLedger;
use App\Services\Solana\FeeVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuessController extends Controller
{
    public function __construct(
        private readonly BundleGuessService $bundles,
        private readonly FeeVerifier $fees,
        private readonly FeeLedger $ledger,
        private readonly FeeTier $feeTier,
        private readonly SpamGuard $spam,
        private readonly AttemptPolicy $policy,
    ) {}

    /**
     * Submit all answers for a week in one paid attempt.
     * Response only includes how many were correct — never which.
     */
    public function submitBundle(Request $request, int $weekNumber): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:120'],
            'fee_signature' => ['required', 'string', 'max:128'],
            'username' => ['nullable', 'string', 'max:64'],
            'payout_address' => ['nullable', 'string', 'min:32', 'max:64'],
        ]);

        $walletId = $request->session()->get('wallet_id');
        $wallet = $walletId ? Wallet::find($walletId) : null;
        if ($wallet === null) {
            return response()->json(['error' => 'wallet_not_connected'], 401);
        }

        if (! $wallet->canPlay()) {
            return response()->json(['error' => 'not_eligible'], 403);
        }

        $week = Week::query()->where('number', $weekNumber)->with('words')->first();
        if ($week === null) {
            return response()->json(['error' => 'week_not_found'], 404);
        }
        if (! $week->isUnlocked()) {
            return response()->json(['error' => 'week_locked'], 403);
        }

        $wordCount = $week->words->count();
        if ($wordCount === 0) {
            return response()->json(['error' => 'week_empty'], 422);
        }

        // Answers may arrive as a list (index 0 = position 1) or a map of positions.
        $answersByPosition = $this->normalizeAnswers($data['answers'], $wordCount);
        if ($answersByPosition === null) {
            return response()->json([
                'error' => 'incomplete_answers',
                'message' => "Submit all {$wordCount} answers before playing.",
            ], 422);
        }

        if ($this->bundles->hasCompleted($wallet, $week)) {
            return response()->json([
                'error' => 'already_completed',
                'message' => 'You have already cleared this week. You have not been charged.',
            ], 409);
        }

        $unlimited = $this->policy->hasUnlimitedAttempts($week);
        $attemptsAllowed = $this->policy->attemptsAllowedPerWeek($wallet, $week);
        $attemptsUsed = $this->bundles->attemptsUsed($wallet, $week);

        if (! $unlimited && $attemptsUsed >= $attemptsAllowed) {
            return response()->json([
                'error' => 'no_attempts_left',
                'message' => 'No bundle attempts remaining for this week. You have not been charged.',
                'attempts_used' => $attemptsUsed,
                'attempts_allowed' => $attemptsAllowed,
                'attempts_left' => 0,
            ], 409);
        }

        if (! $this->spam->isAllowed($wallet)) {
            $retryAfter = $this->spam->retryAfter($wallet);

            SendTelegramMessage::dispatch(sprintf(
                '[TZLA] ⚠️ Spam warning: wallet %s exceeded %d guesses/min. Retry in %ds.',
                $wallet->shortAddress(),
                $this->spam->maxPerWindow(),
                $retryAfter,
            ));

            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => sprintf(
                    '⚠️ Slow down! Max %d submissions per minute. Try again in %d seconds.',
                    $this->spam->maxPerWindow(),
                    $retryAfter,
                ),
                'retry_after' => $retryAfter,
            ], 429);
        }

        $feeSignature = trim($data['fee_signature']);
        $expectedSol = $this->feeTier->amountSol($wallet);
        $toleranceSol = (int) config('game.submission_fees.tolerance_lamports', 1000) / 1_000_000_000;

        if ($this->ledger->alreadySpent($feeSignature)) {
            return $this->replayResponse($expectedSol);
        }

        $paidSol = $this->fees->verifySubmissionFee($feeSignature, $wallet->address);

        if ($paidSol === null || $paidSol < ($expectedSol - $toleranceSol)) {
            return response()->json([
                'error' => 'invalid_fee_payment',
                'message' => sprintf(
                    'Payment not confirmed. Required: %s SOL (%s).',
                    rtrim(rtrim(number_format($expectedSol, 4, '.', ''), '0'), '.'),
                    $this->feeTier->label($wallet),
                ),
                'required_sol' => $expectedSol,
                'treasury' => config('game.submission_fees.treasury_address'),
            ], 402);
        }

        if (! $this->ledger->claimForWeek($feeSignature, $wallet, $week, $paidSol)) {
            return $this->replayResponse($expectedSol);
        }

        $walletUpdates = array_filter([
            'username' => $data['username'] ?? null,
            'payout_address' => $data['payout_address'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '');

        try {
            if ($walletUpdates !== []) {
                $wallet->forceFill($walletUpdates)->save();
            }

            $result = $this->bundles->submit($wallet, $week, $answersByPosition, $feeSignature);
        } catch (\Throwable $e) {
            report($e);
            FeePayment::query()
                ->where('signature', $feeSignature)
                ->where('wallet_id', $wallet->id)
                ->delete();

            return response()->json([
                'error' => 'guess_failed_after_payment',
                'message' => 'Payment looked valid but the attempt could not be saved. Tap submit again — we will reuse the same payment.',
                'fee_signature' => $feeSignature,
            ], 500);
        }

        $this->spam->record($wallet);

        return response()->json([
            'correct_count' => $result['correct_count'],
            'total_words' => $result['total_words'],
            'is_complete' => $result['is_complete'],
            'unlimited_attempts' => $unlimited,
            'attempts_used' => $result['attempts_used'],
            'attempts_allowed' => $unlimited ? null : $result['attempts_allowed'],
            'attempts_left' => $unlimited ? null : $result['attempts_left'],
            'week_complete' => $result['is_complete'],
            'fee_paid_sol' => round($paidSol, 9),
            'fee_tier' => $this->feeTier->label($wallet),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<int, string>|null
     */
    private function normalizeAnswers(array $answers, int $wordCount): ?array
    {
        $byPosition = [];

        $isList = array_keys($answers) === range(0, count($answers) - 1);
        if ($isList) {
            if (count($answers) !== $wordCount) {
                return null;
            }
            foreach ($answers as $i => $raw) {
                $trimmed = trim((string) $raw);
                if ($trimmed === '') {
                    return null;
                }
                $byPosition[$i + 1] = $trimmed;
            }

            return $byPosition;
        }

        for ($pos = 1; $pos <= $wordCount; $pos++) {
            if (! array_key_exists($pos, $answers) && ! array_key_exists((string) $pos, $answers)) {
                return null;
            }
            $trimmed = trim((string) ($answers[$pos] ?? $answers[(string) $pos]));
            if ($trimmed === '') {
                return null;
            }
            $byPosition[$pos] = $trimmed;
        }

        return $byPosition;
    }

    private function replayResponse(float $expectedSol): JsonResponse
    {
        return response()->json([
            'error' => 'fee_signature_already_used',
            'message' => 'That payment has already been used for a previous attempt. Each submission requires its own payment.',
            'required_sol' => $expectedSol,
            'treasury' => config('game.submission_fees.treasury_address'),
        ], 402);
    }
}
