<?php

declare(strict_types=1);

namespace App\Services\Solana;

final readonly class Holdings
{
    public function __construct(
        public float $tzlaBalance,
        public int $nftCount,
        public int $goldenTicketCount = 0,
        public float $stakedAmount = 0.0,
    ) {}
}
