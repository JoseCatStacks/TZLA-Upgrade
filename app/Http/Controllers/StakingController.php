<?php

namespace App\Http\Controllers;

use App\Jobs\VerifyAndRecordStake;
use App\Jobs\VerifyAndRecordUnstake;
use App\Models\StakeRecord;
use App\Services\JupiterPriceService;
use App\Services\RewardService;
use App\Services\SolanaOracleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StakingController extends Controller
{
    public function recordStake(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wallet'     => ['required', 'string', 'max:64', 'regex:/^[1-9A-HJ-NP-Za-km-z]{32,44}$/'],
            'amount_raw' => ['required', 'string', 'regex:/^\d+$/'],
            'nft_tier'   => ['required', 'integer', 'in:0,1,2,3,4'],
            'stake_tx'   => ['required', 'string', 'max:128'],
        ]);

        if (StakeRecord::where('stake_tx', $data['stake_tx'])->exists()) {
            return response()->json(['ok' => true]);
        }

        VerifyAndRecordStake::dispatch(
            $data['wallet'],
            $data['amount_raw'],
            $data['nft_tier'],
            $data['stake_tx'],
        );

        return response()->json(['ok' => true]);
    }

    public function recordUnstake(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wallet'     => ['required', 'string', 'max:64', 'regex:/^[1-9A-HJ-NP-Za-km-z]{32,44}$/'],
            'unstake_tx' => ['required', 'string', 'max:128'],
        ]);

        if (StakeRecord::where('unstake_tx', $data['unstake_tx'])->exists()) {
            return response()->json(['ok' => true]);
        }

        VerifyAndRecordUnstake::dispatch(
            $data['wallet'],
            $data['unstake_tx'],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Public pool headline stats: cumulative TZLA distributed as rewards and its
     * USD value. Both are derived entirely from the background-warmed Redis
     * oracle (on-chain accounts + Jupiter price), and the assembled payload is
     * itself cached for a few seconds, so this endpoint never fans out to
     * Helius or Jupiter on the visitor path no matter the traffic.
     */
    public function poolStats(SolanaOracleService $oracle, JupiterPriceService $prices): JsonResponse
    {
        $payload = Cache::store(config('oracle.store'))->remember(
            'oracle:pool_stats:v1',
            (int) config('oracle.pool_stats_ttl', 5),
            function () use ($oracle, $prices): array {
                $distributedRaw = $oracle->distributedRaw();
                $decimals       = (int) config('oracle.token_decimals', 9);
                $tokens         = (float) $distributedRaw / (10 ** $decimals);
                $price          = $prices->price();

                return [
                    'distributed_raw'    => $distributedRaw,
                    'distributed_tokens' => $tokens,
                    'price_usd'          => $price,
                    'distributed_usd'    => $price !== null ? round($tokens * $price, 2) : null,
                    'as_of'              => now()->timestamp,
                ];
            }
        );

        return response()->json($payload);
    }

    /**
     * Projected weekly + monthly staking rewards for a wallet, in TZLA (and USD
     * when a price is known): what its open positions will earn over the next
     * 7 / 30 days. Derived entirely from the verified stake_records table and
     * the Redis-warmed price oracle, and cached per wallet — never hits Helius.
     */
    public function rewards(Request $request, string $wallet, RewardService $rewards): JsonResponse
    {
        return response()->json($rewards->summary($wallet));
    }

    public function predictedYield(Request $request, string $wallet): JsonResponse
    {
        // Cache the DB lookup for 15 s; the record row itself is immutable once staked.
        // Yield values are recomputed fresh from staked_at on each cache miss.
        $record = Cache::remember(
            "staking:record:{$wallet}",
            15,
            fn () => StakeRecord::where('wallet', $wallet)
                ->whereNull('unstaked_at')
                ->latest('staked_at')
                ->first()
        );

        if (! $record) {
            return response()->json([
                'found'        => false,
                'yield_tokens' => '0',
                'yield_raw'    => '0',
                'staked_at'    => null,
            ]);
        }

        $asOf = now();

        return response()->json([
            'found'           => true,
            'staked_at'       => $record->staked_at->toIso8601String(),
            'amount_raw'      => $record->amount_raw,
            'nft_tier'        => $record->nft_tier,
            'elapsed_seconds' => $asOf->timestamp - $record->staked_at->timestamp,
            'yield_raw'       => $record->predictedYieldRaw($asOf),
            'yield_tokens'    => $record->predictedYieldTokens($asOf),
        ]);
    }
}
