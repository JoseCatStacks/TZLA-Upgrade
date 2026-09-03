<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramMessage;
use App\Models\Wallet;
use App\Models\Week;
use App\Services\Guess\AttemptPolicy;
use App\Services\Guess\GuessService;
use App\Services\Guess\SpamGuard;
use App\Services\Solana\FeeLedger;
use App\Services\Solana\FeeVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuessController extends Controller
{
    public function __construct(
        private readonly GuessService  $guesses,
        private readonly FeeVerifier   $fees,
        private readonly FeeLedger     $ledger,
        private readonly SpamGuard     $spam,
        private readonly AttemptPolicy $policy,
    ) {}

    public function submit(Request $request, int $weekNumber, int $position): JsonResponse
    {
        $data = $request->validate([
            'guess'          => ['required', 'string', 'max:120'],
            'fee_signature'  => ['required', 'string', 'max:128'],
            'username'       => ['nullable', 'string', 'max:64'],
            'payout_address' => ['nullable', 'string', 'min:32', 'max:64'],
        ]);

        $walletId = $request->session()->get('wallet_id');
        $wallet   = $walletId ? Wallet::find($walletId) : null;
        if ($wallet === null) {
            return response()->json(['error' => 'wallet_not_connected'], 401);
        }

        if (! $wallet->canPlay()) {
            return response()->json(['error' => 'not_eligible'], 403);
        }

        // ── Week / word resolution ───────────────────────────────────────────
        $week = Week::query()->where('number', $weekNumber)->first();
        if ($week === null) {
            return response()->json(['error' => 'week_not_found'], 404);
        }
        if (! $week->isUnlocked()) {
            return response()->json(['error' => 'week_locked'], 403);
        }

        $word = $week->words()->where('position', $position)->first();
        if ($word === null) {
            return response()->json(['error' => 'word_not_found'], 404);
        }

        // ── Refuse unplayable guesses BEFORE taking payment ──────────────────
        // These used to be checked inside GuessService, after the fee had already
        // been verified, so a player could be charged for a guess that was then
        // silently discarded.
        $attemptsAllowed = $this->policy->attemptsAllowedPerWord($wallet);
        $attemptsUsed    = $this->guesses->attemptsUsedForWord($wallet, $word);

        if ($this->guesses->hasSolved($wallet, $word)) {
            return response()->json([
                'error'   => 'already_solved',
                'message' => 'You have already solved this word. You have not been charged.',
            ], 409);
        }

        if ($attemptsUsed >= $attemptsAllowed) {
            return response()->json([
                'error'            => 'no_attempts_left',
                'message'          => 'No attempts remaining for this word. You have not been charged.',
                'attempts_used'    => $attemptsUsed,
                'attempts_allowed' => $attemptsAllowed,
                'attempts_left'    => 0,
            ], 409);
        }

        // ── Spam / rate-limit check ──────────────────────────────────────────
        if (! $this->spam->isAllowed($wallet)) {
            $retryAfter = $this->spam->retryAfter($wallet);

            SendTelegramMessage::dispatch(sprintf(
                '[TZLA] ⚠️ Spam warning: wallet %s exceeded %d guesses/min. Retry in %ds.',
                $wallet->shortAddress(),
                $this->spam->maxPerWindow(),
                $retryAfter,
            ));

            return response()->json([
                'error'       => 'rate_limit_exceeded',
                'message'     => sprintf(
                    '⚠️ Slow down! Max %d word guesses per minute. Try again in %d seconds.',
                    $this->spam->maxPerWindow(),
                    $retryAfter,
                ),
                'retry_after' => $retryAfter,
            ], 429);
        }

        // ── Tier-based fee verification ──────────────────────────────────────
        $feeSignature = trim($data['fee_signature']);

        $expectedSol  = $wallet->holdsGoldenTicket()
            ? (float) config('game.submission_fees.golden_ticket_sol', 0.03)
            : (float) config('game.submission_fees.standard_sol',      0.06);

        $toleranceSol = (int) config('game.submission_fees.tolerance_lamports', 1000) / 1_000_000_000;

        // Cheap rejection before spending an RPC round-trip on a known-spent signature.
        if ($this->ledger->alreadySpent($feeSignature)) {
            return $this->replayResponse($expectedSol);
        }

        $paidSol = $this->fees->verifySubmissionFee($feeSignature, $wallet->address);

        if ($paidSol === null || $paidSol < ($expectedSol - $toleranceSol)) {
            return response()->json([
                'error'        => 'invalid_fee_payment',
                'message'      => sprintf(
                    'Payment not confirmed. Required: %s SOL (%s). Please send the correct amount to the treasury and include the transaction signature.',
                    rtrim(rtrim(number_format($expectedSol, 4, '.', ''), '0'), '.'),
                    $wallet->holdsGoldenTicket() ? 'golden ticket rate' : 'standard rate',
                ),
                'required_sol' => $expectedSol,
                'treasury'     => config('game.submission_fees.treasury_address'),
            ], 402);
        }

        // Claim the signature so it can never fund a second guess. The unique
        // index makes this safe even if two requests race here simultaneously.
        if (! $this->ledger->claim($feeSignature, $wallet, $word, $paidSol)) {
            return $this->replayResponse($expectedSol);
        }

        // ── Record spam-guard hit AFTER payment is confirmed ─────────────────
        $this->spam->record($wallet);

        // ── Optional wallet metadata ─────────────────────────────────────────
        $walletUpdates = array_filter([
            'username'       => $data['username']       ?? null,
            'payout_address' => $data['payout_address'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '');

        if ($walletUpdates !== []) {
            $wallet->forceFill($walletUpdates)->save();
        }

        $result = $this->guesses->submit($wallet, $word, $data['guess']);

        return response()->json([
            'is_correct'       => $result->isCorrect,
            'attempts_used'    => $result->attemptsUsed,
            'attempts_allowed' => $result->attemptsAllowed,
            'attempts_left'    => $result->attemptsLeft(),
            'solved_count'     => $result->solvedCount,
            'total_words'      => $result->totalWords,
            'week_complete'    => $result->weekComplete,
            'fee_paid_sol'     => round($paidSol, 9),
        ]);
    }

    private function replayResponse(float $expectedSol): JsonResponse
    {
        return response()->json([
            'error'        => 'fee_signature_already_used',
            'message'      => 'That payment has already been used for a previous guess. Each guess requires its own payment.',
            'required_sol' => $expectedSol,
            'treasury'     => config('game.submission_fees.treasury_address'),
        ], 402);
    }
}
