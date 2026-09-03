<?php

declare(strict_types=1);

namespace App\Services\Solana;

final class StubHoldingsVerifier implements HoldingsVerifier
{
    public function __construct(
        private readonly float $tzlaBalance = 1.0,
        private readonly int $nftCount = 0,
        private readonly int $goldenTicketCount = 0,
    ) {}

    public function holdings(string $address): Holdings
    {
        return new Holdings($this->tzlaBalance, $this->nftCount, $this->goldenTicketCount);
    }
}
