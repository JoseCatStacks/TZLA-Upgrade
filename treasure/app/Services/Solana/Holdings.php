<?php

declare(strict_types=1);

namespace App\Services\Solana;

final readonly class Holdings
{
    public function __construct(
        public float $tzlaBalance,
        public int $nftCount,
        public int $goldenTicketCount = 0,
    ) {}

    public function holdsTzla(): bool
    {
        return $this->tzlaBalance > 0.0;
    }
}
