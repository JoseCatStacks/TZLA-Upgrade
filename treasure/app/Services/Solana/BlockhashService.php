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
        $url = rtrim($this->rpcUrl, '/').'/?api-key='.$this->apiKey;

        try {
            $response = Http::timeout(10)->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 'tzla-blockhash',
                'method'  => 'getLatestBlockhash',
                'params'  => [['commitment' => $this->commitment]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('BlockhashService RPC failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning('BlockhashService RPC non-2xx', ['status' => $response->status()]);

            return null;
        }

        $blockhash = data_get($response->json(), 'result.value.blockhash');
        if (! is_string($blockhash) || $blockhash === '') {
            return null;
        }

        return [
            'blockhash' => $blockhash,
            'last_valid_block_height' => (int) data_get($response->json(), 'result.value.lastValidBlockHeight', 0),
        ];
    }
}
