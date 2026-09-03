<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;

/**
 * Resolves the per-bundle SOL fee for a wallet.
 *
 * Tiers (best / lowest wins):
 *  - Golden Ticket → golden fee
 *  - NFT or staked TZLA or liquid TZLA ≥ mid threshold → mid fee
 *  - Otherwise eligible holder → standard fee
 */
final class FeeTier
{
    public function amountSol(Wallet $wallet): float
    {
        if ($wallet->holdsGoldenTicket()) {
            return (float) config('game.submission_fees.golden_ticket_sol', 0.03);
        }

        if ($wallet->holdsNft()
            || $wallet->hasStaked()
            || $wallet->tzlaBalance() >= (float) config('game.play_gate.tzla_mid_threshold', 33.0)
        ) {
            return (float) config('game.submission_fees.mid_sol', 0.06);
        }

        return (float) config('game.submission_fees.standard_sol', 0.09);
    }

    public function label(Wallet $wallet): string
    {
        if ($wallet->holdsGoldenTicket()) {
            return 'golden ticket';
        }
        if ($wallet->holdsNft()) {
            return 'NFT holder';
        }
        if ($wallet->hasStaked()) {
            return 'staked TZLA';
        }
        if ($wallet->tzlaBalance() >= (float) config('game.play_gate.tzla_mid_threshold', 33.0)) {
            return '33+ TZLA';
        }

        return 'standard';
    }
}
