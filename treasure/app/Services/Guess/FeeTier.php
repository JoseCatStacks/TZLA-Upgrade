<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;

/**
 * Resolves the per-bundle SOL fee for a wallet.
 *
 * Tiers (best / lowest wins):
 *  - Golden Ticket              → 0.03
 *  - TZLA NFT or staked TZLA    → 0.06
 *  - Eligible TZLA holder       → 0.09
 */
final class FeeTier
{
    public function amountSol(Wallet $wallet): float
    {
        if ($wallet->holdsGoldenTicket()) {
            return (float) config('game.submission_fees.golden_ticket_sol', 0.03);
        }

        if ($wallet->holdsNft() || $wallet->hasStaked()) {
            return (float) config('game.submission_fees.mid_sol', 0.06);
        }

        return (float) config('game.submission_fees.standard_sol', 0.09);
    }

    public function label(Wallet $wallet): string
    {
        if ($wallet->holdsGoldenTicket()) {
            return 'Golden Ticket';
        }
        if ($wallet->holdsNft()) {
            return 'NFT holder';
        }
        if ($wallet->hasStaked()) {
            return 'staked TZLA';
        }

        return 'TZLA holder';
    }
}
