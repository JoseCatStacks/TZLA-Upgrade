<?php

declare(strict_types=1);

return [

    'play_gate' => [
        'tzla_threshold'  => (float) env('GAME_TZLA_THRESHOLD', 9.0),
        'require_holdings' => true,
    ],

    'submission_fees' => [
        // SOL charged per word guess, tiered by holder type.
        'golden_ticket_sol' => (float) env('GAME_FEE_GOLDEN_TICKET_SOL', 0.03),
        'standard_sol'      => (float) env('GAME_FEE_STANDARD_SOL',      0.06),

        // Maximum acceptable deviation from the expected fee (1 000 lamports ≈ $0.0002).
        'tolerance_lamports' => (int) env('GAME_FEE_TOLERANCE_LAMPORTS', 1000),

        // How recent a fee transaction must be to count. Prevents a wallet from
        // recycling an old payment it made for some unrelated purpose.
        'max_age_seconds' => (int) env('GAME_FEE_MAX_AGE_SECONDS', 3600),

        // All guess fees are sent here. Deliberately has no default: an accidental
        // fallback would silently route real money to the wrong wallet.
        'treasury_address' => env('GAME_TREASURY_ADDRESS'),
    ],

    'attempts' => [
        // Every eligible wallet gets this many attempts per word...
        'base_per_word' => (int) env('GAME_ATTEMPTS_BASE', 1),

        // ...plus one per NFT held, up to this ceiling. Without a cap a large
        // holder would effectively be able to brute-force every answer.
        'max_per_word' => (int) env('GAME_ATTEMPTS_MAX', 5),
    ],

    'spam' => [
        // Maximum word guesses a wallet may submit in a 60-second window.
        'max_guesses_per_minute' => (int) env('GAME_SPAM_MAX_PER_MINUTE', 10),
    ],

    'winners' => [
        'log_channel' => env('GAME_WINNERS_LOG_CHANNEL', 'winners'),
    ],

];
