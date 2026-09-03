<?php

declare(strict_types=1);

namespace App\Services\Solana;

interface HoldingsVerifier
{
    public function holdings(string $address): Holdings;
}
