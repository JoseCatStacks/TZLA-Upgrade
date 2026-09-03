<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function welcome(): View
    {
        return view('welcome');
    }

    public function staking(): View
    {
        return view('staking', [
            'rpc' => '/api/rpc',
            'programId' => (string) config('staking.program_id'),
            'stakeTokenMint' => (string) config('staking.token_mint'),
            'nftCollection' => (string) config('staking.nft_collection'),
            'poolOwner' => (string) config('staking.pool_owner'),
            'navConnectBtn' => true,
            'active' => 'staking',
        ]);
    }
}
