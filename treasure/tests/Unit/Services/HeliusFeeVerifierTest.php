<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Solana\HeliusFeeVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class HeliusFeeVerifierTest extends TestCase
{
    private const PAYER    = 'PayerWallet1111111111111111111111111111111';
    private const TREASURY = 'Treasury111111111111111111111111111111111';
    private const OUTSIDER = 'Outsider1111111111111111111111111111111111';

    private function verifier(int $maxAge = 3600): HeliusFeeVerifier
    {
        return new HeliusFeeVerifier(
            apiKey: 'test-key',
            rpcUrl: 'https://rpc.test',
            treasuryAddress: self::TREASURY,
            toleranceLamports: 1000,
            maxAgeSeconds: $maxAge,
        );
    }

    /**
     * @param  list<string>  $accountKeys
     * @param  list<int>  $pre
     * @param  list<int>  $post
     */
    private function fakeTransaction(
        array $accountKeys,
        array $pre,
        array $post,
        int $numRequiredSignatures = 1,
        ?int $blockTime = null,
        mixed $err = null,
    ): void {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'result' => [
                    'blockTime' => $blockTime ?? time(),
                    'meta' => [
                        'err' => $err,
                        'preBalances' => $pre,
                        'postBalances' => $post,
                    ],
                    'transaction' => [
                        'message' => [
                            'header' => ['numRequiredSignatures' => $numRequiredSignatures],
                            'accountKeys' => $accountKeys,
                        ],
                    ],
                ],
            ]),
        ]);
    }

    public function test_accepts_a_genuine_payment_from_the_signer(): void
    {
        // Payer sends 0.06 SOL (60,000,000 lamports) plus a 5,000 lamport network fee.
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY],
            pre: [1_000_000_000, 0],
            post: [939_995_000, 60_000_000],
        );

        $this->assertSame(0.06, $this->verifier()->verifySubmissionFee('sig', self::PAYER));
    }

    public function test_rejects_a_wallet_that_only_appears_as_a_non_signer(): void
    {
        // The outsider is referenced by the transaction but did not sign it.
        // The old implementation scanned all account keys and accepted this.
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY, self::OUTSIDER],
            pre: [1_000_000_000, 0, 500],
            post: [939_995_000, 60_000_000, 500],
            numRequiredSignatures: 1,
        );

        $this->assertNull($this->verifier()->verifySubmissionFee('sig', self::OUTSIDER));
    }

    public function test_rejects_when_a_co_signer_did_not_fund_the_transfer(): void
    {
        // Two signers, but the payment came entirely out of the payer's balance.
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::OUTSIDER, self::TREASURY],
            pre: [1_000_000_000, 750_000, 0],
            post: [939_995_000, 750_000, 60_000_000],
            numRequiredSignatures: 2,
        );

        $this->assertNull($this->verifier()->verifySubmissionFee('sig', self::OUTSIDER));
    }

    public function test_rejects_a_stale_transaction(): void
    {
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY],
            pre: [1_000_000_000, 0],
            post: [939_995_000, 60_000_000],
            blockTime: time() - 86_400,
        );

        $this->assertNull($this->verifier()->verifySubmissionFee('sig', self::PAYER));
    }

    public function test_rejects_a_failed_transaction(): void
    {
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY],
            pre: [1_000_000_000, 0],
            post: [939_995_000, 60_000_000],
            err: ['InstructionError' => [0, 'Custom']],
        );

        $this->assertNull($this->verifier()->verifySubmissionFee('sig', self::PAYER));
    }

    public function test_rejects_when_the_treasury_is_not_credited(): void
    {
        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY],
            pre: [1_000_000_000, 60_000_000],
            post: [999_995_000, 60_000_000],
        );

        $this->assertNull($this->verifier()->verifySubmissionFee('sig', self::PAYER));
    }

    public function test_rejects_everything_when_no_treasury_is_configured(): void
    {
        $verifier = new HeliusFeeVerifier(
            apiKey: 'test-key',
            rpcUrl: 'https://rpc.test',
            treasuryAddress: '',
        );

        $this->fakeTransaction(
            accountKeys: [self::PAYER, self::TREASURY],
            pre: [1_000_000_000, 0],
            post: [939_995_000, 60_000_000],
        );

        $this->assertNull($verifier->verifySubmissionFee('sig', self::PAYER));
    }
}
