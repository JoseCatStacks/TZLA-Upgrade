<?php

declare(strict_types=1);

namespace App\Services\Solana;

use App\Models\Wallet;

final class WalletHoldingsSync
{
    public function __construct(private readonly HoldingsVerifier $verifier) {}

    public function refresh(Wallet $wallet): Wallet
    {
        $holdings = $this->verifier->holdings($wallet->address);

        $wallet->forceFill([
            'tzla_balance_cached'        => $holdings->tzlaBalance,
            'staked_amount_cached'       => $holdings->stakedAmount,
            'nft_count_cached'           => $holdings->nftCount,
            'golden_ticket_count_cached' => $holdings->goldenTicketCount,
            'holdings_refreshed_at'      => now(),
        ])->save();

        return $wallet;
    }
}
