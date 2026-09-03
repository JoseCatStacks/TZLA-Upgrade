<?php

declare(strict_types=1);

namespace App\Services\Solana;

interface FeeVerifier
{
    /**
     * Verify that a submitted Solana transaction pays one of the allowed submission fees
     * from `$fromAddress` to the configured treasury.
     *
     * Returns the matched fee amount in SOL on success, or null if invalid / unmatched.
     */
    public function verifySubmissionFee(string $txSignature, string $fromAddress): ?float;
}
