<?php

declare(strict_types=1);

return [

    'play_gate' => [
        'tzla_threshold' => (float) env('GAME_TZLA_THRESHOLD', 9.0),
        // Liquid TZLA at or above this gets the mid fee tier (with NFT / staked).
        'tzla_mid_threshold' => (float) env('GAME_TZLA_MID_THRESHOLD', 33.0),
        'require_holdings' => true,
    ],

    'submission_fees' => [
        // Bundle fee tiers (lowest / best wins):
        //   golden ticket                         → golden_ticket_sol
        //   NFT | staked | liquid TZLA ≥ mid      → mid_sol
        //   otherwise eligible                    → standard_sol
        'golden_ticket_sol' => (float) env('GAME_FEE_GOLDEN_TICKET_SOL', 0.03),
        'mid_sol'           => (float) env('GAME_FEE_MID_SOL', 0.06),
        'standard_sol'      => (float) env('GAME_FEE_STANDARD_SOL', 0.09),

        'tolerance_lamports' => (int) env('GAME_FEE_TOLERANCE_LAMPORTS', 1000),
        'max_age_seconds' => (int) env('GAME_FEE_MAX_AGE_SECONDS', 3600),
        'treasury_address' => env('GAME_TREASURY_ADDRESS'),
    ],

    'attempts' => [
        // Bundle attempts per week: base + 1 per NFT, capped.
        // Weeks listed in weeks.unlimited_attempts ignore this cap.
        'base_per_week' => (int) env('GAME_ATTEMPTS_BASE', 1),
        'max_per_week' => (int) env('GAME_ATTEMPTS_MAX', 5),
        // Legacy aliases used by older tests/code paths.
        'base_per_word' => (int) env('GAME_ATTEMPTS_BASE', 1),
        'max_per_word' => (int) env('GAME_ATTEMPTS_MAX', 5),
    ],

    /*
    | Launch / rollout controls
    | -------------------------
    | max_playable: weeks with number > this stay locked (even if starts_at
    | is in the past). Raise this env when opening the next week.
    |
    | unlimited_attempts: comma-separated week numbers that ignore the
    | attempt cap (fee still required every submit). Default: week 1 only.
    */
    'weeks' => [
        'max_playable' => (int) env('GAME_MAX_PLAYABLE_WEEK', 1),
        'unlimited_attempts' => array_values(array_filter(array_map(
            static fn (string $n): int => (int) trim($n),
            explode(',', (string) env('GAME_UNLIMITED_ATTEMPT_WEEKS', '1')),
        ), static fn (int $n): bool => $n > 0)),
    ],

    'spam' => [
        'max_guesses_per_minute' => (int) env('GAME_SPAM_MAX_PER_MINUTE', 10),
    ],

    'winners' => [
        'log_channel' => env('GAME_WINNERS_LOG_CHANNEL', 'winners'),
    ],

];
