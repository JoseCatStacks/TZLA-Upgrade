<?php

return [

    /*
    | Recovered from the deployed program (lib.rs declare_id! + constants)
    | and the live pool account on mainnet.
    */

    'program_id' => env('STAKING_PROGRAM_ID', '3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ'),

    // Wallet that initialized the pool PDA: seeds = ["stake_pool", pool_owner]
    'pool_owner' => env('STAKING_POOL_OWNER', 'TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3'),

    'token_mint' => env('STAKING_TOKEN_MINT', '4tWMJCW6tdpVUkwDpX1NEQURbtuQDg7H9DfkjEpGnq5D'),

    'nft_collection' => env('STAKING_NFT_COLLECTION', '8cTpLj5JkptcbYfonkRWMaa7MRAsrqqxHQYKz6J1rTQw'),

    // Daily rate = numerator / rate_denominator. Matches lib.rs.
    'rate_numerators' => [
        0 => (int) env('STAKING_RATE_BASE', 69),
        1 => (int) env('STAKING_RATE_NFT1', 111),
        2 => (int) env('STAKING_RATE_NFT2', 222),
        3 => (int) env('STAKING_RATE_GOLDEN', 369),
        4 => (int) env('STAKING_RATE_NFT10', 330),
    ],

    'rate_denominator' => (string) env('STAKING_RATE_DENOMINATOR', '100000'),

    'seconds_per_day' => (string) env('STAKING_SECONDS_PER_DAY', '86400'),

    'token_base_units' => (string) env('STAKING_TOKEN_BASE_UNITS', '1000000000'),

    'rewards_cache_ttl' => (int) env('STAKING_REWARDS_CACHE_TTL', 15),

];
