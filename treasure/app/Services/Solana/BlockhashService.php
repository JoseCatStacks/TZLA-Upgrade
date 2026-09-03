<?php

declare(strict_types=1);

namespace App\Services\Solana;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Supplies a recent blockhash so the browser can build a fee transaction
 * without ever seeing the Helius API key.
 */
final class BlockhashService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $rpcUrl,
        private readonly string $commitment = 'confirmed',
    ) {}

    /**
     * @return array{blockhash: string, last_valid_block_height: int}|null
     */
    public function latest(): ?array
    {
        $result = $this->rpc('getLatestBlockhash', [['commitment' => $this->commitment]]);
        $blockhash = data_get($result, 'value.blockhash');
        if (! is_string($blockhash) || $blockhash === '') {
            return null;
        }

        return [
            'blockhash' => $blockhash,
            'last_valid_block_height' => (int) data_get($result, 'value.lastValidBlockHeight', 0),
        ];
    }

    /**
     * Broadcast a pre-signed transaction via Helius. Returns the signature or null.
     */
    public function sendRaw(string $base64Transaction): ?string
    {
        $raw = base64_decode($base64Transaction, true);
        if ($raw === false || $raw === '') {
            return null;
        }

        // Solana JSON-RPC expects the wire transaction as a base64 string.
        $signature = $this->rpc('sendTransaction', [
            $base64Transaction,
            [
                'encoding'            => 'base64',
                'skipPreflight'       => false,
                'preflightCommitment' => $this->commitment,
            ],
        ]);

        return is_string($signature) && $signature !== '' ? $signature : null;
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    private function rpc(string $method, array $params): mixed
    {
        $url = rtrim($this->rpcUrl, '/').'/?api-key='.$this->apiKey;

        try {
            $response = Http::timeout(20)->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 'tzla-'.$method,
                'method'  => $method,
                'params'  => $params,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BlockhashService RPC failed', ['method' => $method, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('BlockhashService RPC non-2xx', [
                'method' => $method,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        if (data_get($json, 'error')) {
            Log::warning('BlockhashService RPC error', [
                'method' => $method,
                'error'  => data_get($json, 'error'),
            ]);

            return null;
        }

        return data_get($json, 'result');
    }
}
