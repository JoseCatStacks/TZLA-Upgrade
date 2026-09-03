<?php

declare(strict_types=1);

namespace App\Services\Solana;

final class StubHoldingsVerifier implements HoldingsVerifier
{
    public function __construct(
        private readonly float $tzlaBalance = 100.0,
        private readonly int $nftCount = 0,
        private readonly int $goldenTicketCount = 0,
        private readonly float $stakedAmount = 0.0,
    ) {}

    public function holdings(string $address): Holdings
    {
        return new Holdings(
            tzlaBalance: $this->tzlaBalance,
            nftCount: $this->nftCount,
            goldenTicketCount: $this->goldenTicketCount,
            stakedAmount: $this->stakedAmount,
        );
    }
}
