<?php

declare(strict_types=1);

namespace App\Services\Solana;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class HeliusHoldingsVerifier implements HoldingsVerifier
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $rpcUrl,
        private readonly ?string $tzlaMint,
        private readonly ?string $nftCollectionMint,
        private readonly ?string $goldenTicketClassicCollection,
        private readonly ?string $goldenTicketCnftCollection,
        private readonly int $cacheTtl = 300,
    ) {}

    public function holdings(string $address): Holdings
    {
        return Cache::remember(
            "holdings:{$address}",
            $this->cacheTtl,
            function () use ($address): Holdings {
                $nfts = $this->fetchNftCounts($address);

                return new Holdings(
                    tzlaBalance: $this->fetchTzlaBalance($address),
                    nftCount: $nfts['nftCount'],
                    goldenTicketCount: $nfts['goldenTicketCount'],
                );
            },
        );
    }

    private function fetchTzlaBalance(string $owner): float
    {
        if ($this->tzlaMint === null || $this->tzlaMint === '') {
            return 0.0;
        }

        $response = $this->rpc('getTokenAccountsByOwner', [
            $owner,
            ['mint' => $this->tzlaMint],
            ['encoding' => 'jsonParsed'],
        ]);

        $accounts = data_get($response, 'result.value', []);
        $total = 0.0;
        foreach ($accounts as $account) {
            $amount = (float) data_get($account, 'account.data.parsed.info.tokenAmount.uiAmount', 0);
            $total += $amount;
        }

        return $total;
    }

    /**
     * Walks all assets owned by $owner in a single paginated getAssetsByOwner pass
     * and buckets them by collection into TZLA Core NFTs and Golden Tickets.
     *
     * @return array{nftCount: int, goldenTicketCount: int}
     */
    private function fetchNftCounts(string $owner): array
    {
        if ($this->nftCollectionMint === null && $this->goldenTicketClassicCollection === null && $this->goldenTicketCnftCollection === null) {
            return ['nftCount' => 0, 'goldenTicketCount' => 0];
        }

        $nftCount = 0;
        $goldenTicketCount = 0;
        $page = 1;
        $limit = 1000;

        while (true) {
            $response = $this->rpc('getAssetsByOwner', [
                'ownerAddress' => $owner,
                'page' => $page,
                'limit' => $limit,
                'displayOptions' => ['showCollectionMetadata' => false],
            ]);

            $items = data_get($response, 'result.items', []);

            foreach ($items as $item) {
                $collection = null;
                foreach (data_get($item, 'grouping', []) as $group) {
                    if (data_get($group, 'group_key') === 'collection') {
                        $collection = data_get($group, 'group_value');
                        break;
                    }
                }

                if ($collection === null) {
                    continue;
                }

                if ($collection === $this->goldenTicketClassicCollection
                    || $collection === $this->goldenTicketCnftCollection) {
                    $goldenTicketCount++;
                } elseif ($collection === $this->nftCollectionMint) {
                    $nftCount++;
                }
            }

            if (count($items) < $limit) {
                break;
            }

            $page++;
        }

        return ['nftCount' => $nftCount, 'goldenTicketCount' => $goldenTicketCount];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params): array
    {
        $url = rtrim($this->rpcUrl, '/').'/?api-key='.$this->apiKey;

        try {
            $response = Http::timeout(10)->post($url, [
                'jsonrpc' => '2.0',
                'id' => 'tzla-'.$method,
                'method' => $method,
                'params' => $params,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Helius RPC failed', ['method' => $method, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->ok()) {
            Log::warning('Helius RPC non-2xx', ['method' => $method, 'status' => $response->status()]);

            return [];
        }

        return $response->json() ?? [];
    }
}
