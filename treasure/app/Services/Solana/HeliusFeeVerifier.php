<?php

declare(strict_types=1);

namespace App\Services\Solana;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class HeliusFeeVerifier implements FeeVerifier
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $rpcUrl,
        private readonly string $treasuryAddress,
        private readonly int $toleranceLamports = 1000,
        private readonly int $maxAgeSeconds = 3600,
        private readonly string $commitment = 'confirmed',
    ) {}

    /**
     * Verifies that the given transaction:
     *  - is confirmed on-chain and did not fail
     *  - was actually *signed* by $fromAddress (not merely referenced by it)
     *  - transferred SOL to the configured treasury, funded by that signer
     *  - is recent enough to belong to this play session
     *
     * Returns the SOL amount received by the treasury, or null if verification fails.
     */
    public function verifySubmissionFee(string $txSignature, string $fromAddress): ?float
    {
        $txSignature = trim($txSignature);
        $fromAddress = trim($fromAddress);

        if ($txSignature === '' || $fromAddress === '') {
            return null;
        }

        if (trim($this->treasuryAddress) === '') {
            Log::error('HeliusFeeVerifier: no treasury address configured; refusing all fees. Set GAME_TREASURY_ADDRESS.');

            return null;
        }

        // A payment "from" the treasury to itself would net out as a credit.
        if ($fromAddress === $this->treasuryAddress) {
            return null;
        }

        $tx = null;
        // Wallets return as soon as the tx is submitted. Poll briefly so a
        // freshly paid fee is not rejected as "not found" and lost.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->rpc('getTransaction', [
                $txSignature,
                [
                    'encoding'                       => 'json',
                    'commitment'                     => $this->commitment,
                    'maxSupportedTransactionVersion' => 0,
                ],
            ]);

            $tx = data_get($response, 'result');
            if ($tx !== null) {
                break;
            }

            if ($attempt < 10) {
                usleep(500_000);
            }
        }

        if ($tx === null) {
            Log::info('HeliusFeeVerifier: transaction not found or not confirmed', ['sig' => $txSignature]);

            return null;
        }

        // Bail if the transaction itself failed.
        if (data_get($tx, 'meta.err') !== null) {
            return null;
        }

        if (! $this->isRecentEnough($tx, $txSignature)) {
            return null;
        }

        $accountKeys  = $this->accountKeys($tx);
        $preBalances  = data_get($tx, 'meta.preBalances', []);
        $postBalances = data_get($tx, 'meta.postBalances', []);

        if ($accountKeys === [] || count($preBalances) !== count($accountKeys)) {
            return null;
        }

        // Only the first `numRequiredSignatures` accounts actually signed the
        // transaction. Merely appearing in accountKeys proves nothing — it would
        // let anyone claim credit for a stranger's payment.
        $signerIndex = $this->signerIndex($tx, $accountKeys, $fromAddress);
        if ($signerIndex === null) {
            Log::info('HeliusFeeVerifier: wallet did not sign the transaction', [
                'sig'    => $txSignature,
                'wallet' => $fromAddress,
            ]);

            return null;
        }

        $treasuryIndex = array_search($this->treasuryAddress, $accountKeys, true);
        if ($treasuryIndex === false) {
            return null;
        }

        $treasuryDelta = (int) ($postBalances[$treasuryIndex] ?? 0) - (int) ($preBalances[$treasuryIndex] ?? 0);
        if ($treasuryDelta <= 0) {
            return null;
        }

        // The signer must have funded the credit themselves. Otherwise a player
        // could co-sign a transaction that someone else paid for.
        $senderDelta = (int) ($postBalances[$signerIndex] ?? 0) - (int) ($preBalances[$signerIndex] ?? 0);
        $senderPaid  = -$senderDelta;

        if ($senderPaid + $this->toleranceLamports < $treasuryDelta) {
            Log::info('HeliusFeeVerifier: treasury credit was not funded by the signer', [
                'sig'            => $txSignature,
                'wallet'         => $fromAddress,
                'sender_paid'    => $senderPaid,
                'treasury_delta' => $treasuryDelta,
            ]);

            return null;
        }

        return $treasuryDelta / 1_000_000_000;
    }

    /**
     * A fee must be paid for the guess being made, not recycled from an old
     * transaction the wallet happens to have lying around.
     *
     * @param  array<string, mixed>  $tx
     */
    private function isRecentEnough(array $tx, string $txSignature): bool
    {
        if ($this->maxAgeSeconds <= 0) {
            return true;
        }

        $blockTime = data_get($tx, 'blockTime');
        if ($blockTime === null) {
            // Unknown age; treat as unverifiable rather than assuming it is fresh.
            Log::info('HeliusFeeVerifier: transaction has no blockTime', ['sig' => $txSignature]);

            return false;
        }

        $age = time() - (int) $blockTime;
        if ($age > $this->maxAgeSeconds) {
            Log::info('HeliusFeeVerifier: fee transaction too old', [
                'sig'         => $txSignature,
                'age_seconds' => $age,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Account keys are plain strings under `json` encoding but objects under
     * `jsonParsed`. Versioned (v0) transactions also append ALT-loaded addresses
     * in meta.loadedAddresses — balances are indexed across that full list.
     *
     * @param  array<string, mixed>  $tx
     * @return list<string>
     */
    private function accountKeys(array $tx): array
    {
        $keys = data_get($tx, 'transaction.message.accountKeys', []);

        $normalized = [];
        foreach ($keys as $key) {
            if (is_string($key)) {
                $normalized[] = $key;
            } elseif (is_array($key) && isset($key['pubkey'])) {
                $normalized[] = (string) $key['pubkey'];
            } else {
                return [];
            }
        }

        foreach (['writable', 'readonly'] as $group) {
            foreach (data_get($tx, "meta.loadedAddresses.{$group}", []) as $key) {
                if (is_string($key)) {
                    $normalized[] = $key;
                }
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $tx
     * @param  list<string>  $accountKeys
     */
    private function signerIndex(array $tx, array $accountKeys, string $fromAddress): ?int
    {
        $numSigners = (int) data_get($tx, 'transaction.message.header.numRequiredSignatures', 0);
        if ($numSigners <= 0) {
            return null;
        }

        $limit = min($numSigners, count($accountKeys));
        for ($i = 0; $i < $limit; $i++) {
            if ($accountKeys[$i] === $fromAddress) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params): array
    {
        $url = rtrim($this->rpcUrl, '/').'/?api-key='.$this->apiKey;

        try {
            $response = Http::timeout(15)->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 'tzla-fee-'.$method,
                'method'  => $method,
                'params'  => $params,
            ]);
        } catch (\Throwable $e) {
            Log::warning('HeliusFeeVerifier RPC failed', ['method' => $method, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->ok()) {
            Log::warning('HeliusFeeVerifier RPC non-2xx', ['method' => $method, 'status' => $response->status()]);

            return [];
        }

        return $response->json() ?? [];
    }
}
