<?php

return [

    'tabs' => [
        'treasure' => [
            'title' => 'Treasure Hunt',
            'url' => env('PORTAL_TREASURE_URL'),
            'embed' => true,
            'desc' => 'The weekly word hunt. Connect Phantom and play.',
        ],
        'nft' => [
            'title' => 'TZLA NFTs',
            'url' => env('PORTAL_NFT_URL'),
            'embed' => false,
            'desc' => 'Core NFTs that raise your staking tier.',
        ],
        'swap' => [
            'title' => 'Swap',
            'url' => env('PORTAL_SWAP_URL', 'https://jup.ag/swap/SOL-4tWMJCW6tdpVUkwDpX1NEQURbtuQDg7H9DfkjEpGnq5D'),
            'embed' => false,
            'desc' => 'Buy TZLA on Jupiter. Opens in a new tab.',
        ],
    ],

];
