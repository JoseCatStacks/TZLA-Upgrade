<?php

declare(strict_types=1);

return [

    'provider' => env('SOLANA_PROVIDER', 'stub'),

    'network' => env('SOLANA_NETWORK', 'mainnet-beta'),

    'rpc_url' => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),

    'helius' => [
        'api_key' => env('HELIUS_API_KEY'),
        'rpc_url' => env('HELIUS_RPC_URL', 'https://mainnet.helius-rpc.com'),
    ],

    // Commitment level used when verifying fee payments. Use 'finalized' for the
    // strongest guarantee at the cost of ~13s extra confirmation latency.
    'commitment' => env('SOLANA_COMMITMENT', 'confirmed'),

    'tzla_mint' => env('SOLANA_TZLA_MINT'),

    'nft_collection_mint' => env('SOLANA_NFT_COLLECTION'),

    // Golden Ticket NFT collections (hardcoded in the on-chain staking program).
    // Classic Token Metadata NFTs use stake_with_nft_ticket; Bubblegum cNFTs use stake_with_ticket.
    'golden_ticket_classic_collection' => env('SOLANA_GOLDEN_TICKET_CLASSIC', 'FUSkrmKPfJ39fZwJYSgUKYBNcytkWPYsV6n8LB21NB5Q'),
    'golden_ticket_cnft_collection'    => env('SOLANA_GOLDEN_TICKET_CNFT',    'ETKq2GEUDYa5wm2PsNtxXRRn5iWBZyzWLXQ9WvKZptET'),

    'holdings_cache_ttl' => (int) env('SOLANA_HOLDINGS_CACHE_TTL', 300),

    'auth_domain' => env('SOLANA_AUTH_DOMAIN', env('APP_URL', 'http://localhost')),

    'nonce_ttl' => (int) env('SOLANA_NONCE_TTL', 300),

    // Used to detect staked TZLA for fee tiers / eligibility.
    'staking' => [
        'program_id' => env('STAKING_PROGRAM_ID', '3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ'),
        'pool_address' => env('STAKING_POOL_ADDRESS', '2yYgVz8CDzvMFYZ2cfMy854RETrafVYSAAaUUJw9bAVV'),
    ],

];
