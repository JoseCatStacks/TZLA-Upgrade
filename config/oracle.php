<?php

return [

    // database works locally without Redis (supports locks). Use redis in production.
    'store' => env('ORACLE_CACHE_STORE', env('CACHE_STORE', 'database')),

    'lock_seconds' => (int) env('ORACLE_LOCK_SECONDS', 10),

    'staleness_threshold_seconds' => (int) env('ORACLE_STALE_SECONDS', 120),

    'max_batch' => (int) env('ORACLE_MAX_BATCH', 25),

    'pool_stats_ttl' => (int) env('ORACLE_POOL_STATS_TTL', 5),

    'token_decimals' => (int) env('ORACLE_TOKEN_DECIMALS', 9),

    // Optional known total ever deposited into the reward vault (base units).
    // Leave 0 to use the observed high-water mark only.
    'reward_vault_funded_raw' => env('ORACLE_REWARD_VAULT_FUNDED_RAW', '0'),

    'ttl' => [
        'assets' => (int) env('ORACLE_ASSETS_TTL', 30),
    ],

    'global' => [
        'pool' => [
            'address' => env('STAKING_POOL_ADDRESS', '2yYgVz8CDzvMFYZ2cfMy854RETrafVYSAAaUUJw9bAVV'),
            'method' => 'getAccountInfo',
            'params' => ['encoding' => 'base64', 'commitment' => 'confirmed'],
        ],
        'reward_vault' => [
            'address' => env('STAKING_REWARD_VAULT', 'DqjRmDNu3JRpgnUhjBrGERF9Czir569H8BcxBZt5RtQ3'),
            'method' => 'getTokenAccountBalance',
            'params' => ['commitment' => 'confirmed'],
        ],
    ],

    'price' => [
        'mint' => env('STAKING_TOKEN_MINT', '4tWMJCW6tdpVUkwDpX1NEQURbtuQDg7H9DfkjEpGnq5D'),
        'endpoint' => env('JUPITER_PRICE_ENDPOINT', 'https://lite-api.jup.ag/price/v3'),
    ],

];
