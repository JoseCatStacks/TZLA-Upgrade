<?php

declare(strict_types=1);

namespace App\Services\Solana;

final class StubFeeVerifier implements FeeVerifier
{
    /**
     * Non-verifying stub for local/dev. Accepts any non-empty signature and returns
     * the standard fee so the full pipeline runs end-to-end without a real transaction.
     * Switch SOLANA_PROVIDER=helius in production.
     */
    public function verifySubmissionFee(string $txSignature, string $fromAddress): ?float
    {
        if (trim($txSignature) === '') {
            return null;
        }

        // Return the standard (higher) fee so both golden-ticket and standard players pass.
        return (float) config('game.submission_fees.standard_sol', 0.06);
    }
}
