<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class HeliusService
{
    private readonly string $endpoint;

    public function __construct(private readonly SolanaOracleService $oracle)
    {
        $apiKey = (string) config('services.helius.api_key', '');
        $this->endpoint = $apiKey !== ''
            ? "https://mainnet.helius-rpc.com/?api-key={$apiKey}"
            : '';
    }

    private const ALLOWED_RPC_METHODS = [
        'getAccountInfo',
        'getTokenAccountBalance',
        'getLatestBlockhash',
        'getMinimumBalanceForRentExemption',
        'sendTransaction',
        'simulateTransaction',
        'getSignatureStatuses',
        'getBlockHeight',       // blockhash-expiry check in confirmTransaction()
        'getAssetsByOwner',
        'getAsset',             // Golden Ticket (cNFT) metadata reconstruction
        'getAssetProof',        // Golden Ticket (cNFT) Merkle proof
    ];

    /**
     * Serve a whitelisted JSON-RPC payload from the browser.
     *
     * Cacheable reads (pool/vault accounts, getAssetsByOwner) are answered from
     * the Redis oracle without touching Helius; everything else (tx path,
     * per-wallet account reads) is forwarded live. Accepts a single request or a
     * batch array, and returns a JSON-RPC error for any disallowed method.
     */
    public function proxyRpc(mixed $payload): mixed
    {
        if (isset($payload['method'])) {
            return $this->resolveLocally($payload) ?? $this->forward($payload);
        }

        if (! is_array($payload)) {
            return $this->error(null, -32600, 'Invalid request');
        }

        if (count($payload) > (int) config('oracle.max_batch', 25)) {
            return $this->error(null, -32600, 'Batch too large');
        }

        // Answer cacheable entries locally; collect the rest for one live batch,
        // then stitch responses back into the original positions.
        $out  = [];
        $live = [];
        foreach ($payload as $i => $req) {
            $local = $this->resolveLocally($req);
            if ($local !== null) {
                $out[$i] = $local;
            } else {
                $live[$i] = $req;
            }
        }

        if ($live !== []) {
            $forwarded = $this->forward(array_values($live));
            $forwarded = is_array($forwarded) ? array_values($forwarded) : [];
            $j = 0;
            foreach (array_keys($live) as $i) {
                $out[$i] = $forwarded[$j++] ?? null;
            }
        }

        ksort($out);

        return array_values($out);
    }

    /**
     * Resolve a single request without hitting Helius live: a method-not-allowed
     * error, or a cache-served result. Returns null when the request must be
     * forwarded live.
     */
    private function resolveLocally(mixed $req): ?array
    {
        $method = is_array($req) ? ($req['method'] ?? null) : null;

        if (! is_string($method) || ! in_array($method, self::ALLOWED_RPC_METHODS, true)) {
            $this->oracle->recordStat('rejected');

            return $this->error(is_array($req) ? ($req['id'] ?? null) : null, -32601, 'Method not allowed');
        }

        $params = is_array($req) ? ($req['params'] ?? []) : [];
        $kind   = $this->oracle->kindFor($method, $params);

        if ($kind === null) {
            $this->oracle->recordStat('forward');

            return null;
        }

        $this->oracle->recordStat('hit');

        return [
            'jsonrpc' => '2.0',
            'id'      => is_array($req) ? ($req['id'] ?? null) : null,
            'result'  => $this->oracle->resultFor($kind, $params),
        ];
    }

    private function forward(mixed $payload): mixed
    {
        if ($this->endpoint === '') {
            $id = is_array($payload) ? ($payload['id'] ?? null) : null;

            return $this->error($id, -32000, 'Helius API key is not configured');
        }

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->endpoint, $payload);

        return $response->json();
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'error'   => ['code' => $code, 'message' => $message],
            'id'      => $id,
        ];
    }

    /**
     * Fetch a confirmed transaction by signature.
     * Returns null when the transaction is not yet available.
     */
    public function getTransaction(string $signature): ?array
    {
        if ($this->endpoint === '') {
            return null;
        }

        $response = Http::timeout(15)->post($this->endpoint, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'getTransaction',
            'params'  => [
                $signature,
                [
                    'encoding'                       => 'json',
                    'commitment'                     => 'confirmed',
                    'maxSupportedTransactionVersion' => 0,
                ],
            ],
        ]);

        return $response->json('result');
    }

    /**
     * Fetch an account's raw data (base64-decoded bytes). Returns null when the
     * account does not exist or the RPC is unavailable.
     */
    public function getAccountData(string $address): ?string
    {
        if ($this->endpoint === '') {
            return null;
        }

        $response = Http::timeout(15)->post($this->endpoint, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'getAccountInfo',
            'params'  => [
                $address,
                ['encoding' => 'base64', 'commitment' => 'confirmed'],
            ],
        ]);

        $encoded = $response->json('result.value.data.0');
        if (! is_string($encoded)) {
            return null;
        }

        $data = base64_decode($encoded, true);

        return $data === false ? null : $data;
    }
}
