<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Week;
use App\Services\Guess\AttemptPolicy;
use App\Services\Guess\BundleGuessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WeekController extends Controller
{
    public function __construct(
        private readonly BundleGuessService $bundles,
        private readonly AttemptPolicy $policy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->currentWallet($request);

        $weeks = Week::query()
            ->where('active', true)
            ->withCount('words')
            ->orderBy('number')
            ->get();

        return response()->json([
            'weeks' => $weeks->map(function (Week $week) use ($wallet): array {
                $complete = $wallet
                    ? $this->bundles->hasCompleted($wallet, $week)
                    : false;

                return [
                    'number' => $week->number,
                    'title' => $week->title,
                    'is_active' => (bool) $week->active,
                    'is_unlocked' => $week->isUnlocked(),
                    'starts_at' => $week->starts_at?->toIso8601String(),
                    'reward_description' => $week->reward_description,
                    'reward_claimed' => (bool) $week->reward_claimed,
                    // Blind: never expose partial progress as solved_word_count.
                    'solved_word_count' => $complete ? (int) $week->words_count : 0,
                    'week_complete' => $complete,
                    'total_words' => (int) $week->words_count,
                ];
            })->values(),
        ]);
    }

    public function show(Request $request, int $number): JsonResponse
    {
        $week = Week::query()->where('number', $number)->with('words')->first();
        if ($week === null) {
            return response()->json(['error' => 'week_not_found'], 404);
        }
        if (! $week->isUnlocked()) {
            return response()->json(['error' => 'week_locked', 'starts_at' => $week->starts_at?->toIso8601String()], 403);
        }

        $wallet = $this->currentWallet($request);
        $attemptsAllowed = $wallet ? $this->policy->attemptsAllowedPerWeek($wallet) : 0;
        $attemptsUsed = $wallet ? $this->bundles->attemptsUsed($wallet, $week) : 0;
        $weekComplete = $wallet ? $this->bundles->hasCompleted($wallet, $week) : false;

        // Blind until full clear: hints only. On complete, reveal answers.
        $words = $week->words->sortBy('position')->values()->map(function ($word) use ($weekComplete): array {
            $row = [
                'position' => $word->position,
                'hint' => $word->hint,
            ];
            if ($weekComplete) {
                $row['is_solved'] = true;
                $row['solved_answer'] = $word->answer_normalized;
            }

            return $row;
        });

        return response()->json([
            'number' => $week->number,
            'title' => $week->title,
            'reward_description' => $week->reward_description,
            'is_unlocked' => true,
            'week_complete' => $weekComplete,
            'attempts_used' => $attemptsUsed,
            'attempts_allowed' => $attemptsAllowed,
            'attempts_left' => max(0, $attemptsAllowed - $attemptsUsed),
            'total_words' => $week->words->count(),
            'words' => $words,
            'wallet_connected' => $wallet !== null,
        ]);
    }

    private function currentWallet(Request $request): ?Wallet
    {
        $id = $request->session()->get('wallet_id');

        return $id ? Wallet::find($id) : null;
    }
}
