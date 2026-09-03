<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\Week;
use App\Models\WordCompletion;
use App\Services\Guess\AttemptPolicy;
use App\Services\Guess\GuessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WeekController extends Controller
{
    public function __construct(
        private readonly GuessService $guesses,
        private readonly AttemptPolicy $policy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->currentWallet($request);

        // Inactive weeks are drafts. Listing them leaked upcoming titles and
        // reward descriptions even though `show` correctly refuses to open them.
        $weeks = Week::query()
            ->where('active', true)
            ->withCount('words')
            ->orderBy('number')
            ->get();

        $solvedByWeek = [];
        if ($wallet !== null) {
            $solvedByWeek = WordCompletion::query()
                ->where('wallet_id', $wallet->id)
                ->join('words', 'words.id', '=', 'word_completions.word_id')
                ->selectRaw('words.week_id as week_id, COUNT(*) as solved')
                ->groupBy('words.week_id')
                ->pluck('solved', 'week_id')
                ->all();
        }

        return response()->json([
            'weeks' => $weeks->map(fn (Week $week): array => [
                'number' => $week->number,
                'title' => $week->title,
                'is_active' => (bool) $week->active,
                'is_unlocked' => $week->isUnlocked(),
                'starts_at' => $week->starts_at?->toIso8601String(),
                'reward_description' => $week->reward_description,
                'reward_claimed' => (bool) $week->reward_claimed,
                'solved_word_count' => (int) ($solvedByWeek[$week->id] ?? 0),
                'total_words' => (int) $week->words_count,
            ])->values(),
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
        $attemptsAllowed = $wallet ? $this->policy->attemptsAllowedPerWord($wallet) : 0;

        $wordIds = $week->words->pluck('id')->all();
        $solved   = $wallet ? $this->guesses->solvedWordIds($wallet, $wordIds) : [];
        $attempts = $wallet ? $this->guesses->attemptCountsByWord($wallet, $wordIds) : [];

        $words = $week->words->map(function ($word) use ($attemptsAllowed, $solved, $attempts): array {
            $isSolved = isset($solved[$word->id]);
            $used = (int) ($attempts[$word->id] ?? 0);

            return [
                'position' => $word->position,
                'hint' => $word->hint,
                'is_solved' => $isSolved,
                'solved_answer' => $isSolved ? $word->answer_normalized : null,
                'attempts_used' => $used,
                'attempts_allowed' => $attemptsAllowed,
                'attempts_left' => max(0, $attemptsAllowed - $used),
            ];
        });

        $weekComplete = $wallet
            ? $week->completions()->where('wallet_id', $wallet->id)->exists()
            : false;

        return response()->json([
            'number' => $week->number,
            'title' => $week->title,
            'reward_description' => $week->reward_description,
            'is_unlocked' => true,
            'week_complete' => $weekComplete,
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
