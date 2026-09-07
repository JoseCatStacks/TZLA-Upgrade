<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\Guess\FeeTier;
use App\Services\Solana\BlockhashService;
use App\Services\Solana\WalletHoldingsSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes the values the browser needs to build a fee payment. Everything here
 * is public information; the Helius key stays server-side.
 */
final class GameConfigController extends Controller
{
    public function __construct(
        private readonly FeeTier $feeTier,
        private readonly WalletHoldingsSync $holdingsSync,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->currentWallet($request);
        if ($wallet !== null) {
            $this->holdingsSync->refresh($wallet);
        }

        $standard = (float) config('game.submission_fees.standard_sol', 0.09);
        $mid = (float) config('game.submission_fees.mid_sol', 0.06);
        $golden = (float) config('game.submission_fees.golden_ticket_sol', 0.03);

        return response()->json([
            'treasury_address' => config('game.submission_fees.treasury_address'),
            'network' => config('solana.network'),
            'fees' => [
                'standard_sol' => $standard,
                'mid_sol' => $mid,
                'golden_ticket_sol' => $golden,
                'tzla_mid_threshold' => (float) config('game.play_gate.tzla_mid_threshold', 33),
                'tiers' => [
                    ['sol' => $golden, 'label' => 'Golden Ticket'],
                    ['sol' => $mid, 'label' => 'NFT or staked TZLA'],
                    ['sol' => $standard, 'label' => 'TZLA holder (under 33)'],
                ],
            ],
            'max_playable_week' => (int) config('game.weeks.max_playable', 1),
            'unlimited_attempt_weeks' => array_values(array_map(
                'intval',
                config('game.weeks.unlimited_attempts', [1]),
            )),
            'payments_enabled' => strtolower((string) config('solana.provider')) === 'helius',
            'prizes' => [
                'paid_places' => (int) config('game.prizes.paid_places', 5),
                'xmr' => collect(config('game.prizes.xmr', []))
                    ->mapWithKeys(fn ($amt, $place): array => [(int) $place => (float) $amt])
                    ->all(),
            ],
            'your_fee_sol' => $wallet ? $this->feeTier->amountSol($wallet) : $standard,
            'your_fee_tier' => $wallet ? $this->feeTier->label($wallet) : 'standard',
        ]);
    }

    public function blockhash(BlockhashService $blockhashes): JsonResponse
    {
        $result = $blockhashes->latest();

        if ($result === null) {
            return response()->json(['error' => 'blockhash_unavailable'], 503);
        }

        return response()->json($result);
    }

    /**
     * Broadcast a wallet-signed fee transaction so the browser never depends on
     * the public Solana RPC (which often throws after the wallet already sent).
     */
    public function send(Request $request, BlockhashService $blockhashes): JsonResponse
    {
        if ($this->currentWallet($request) === null) {
            return response()->json(['error' => 'wallet_not_connected'], 401);
        }

        $data = $request->validate([
            'transaction' => ['required', 'string', 'max:8192'],
        ]);

        $signature = $blockhashes->sendRaw($data['transaction']);
        if ($signature === null) {
            return response()->json([
                'error' => 'broadcast_failed',
                'message' => 'Could not broadcast the signed payment. Your wallet should not have been charged — if SOL left, contact support with the explorer link.',
            ], 502);
        }

        return response()->json(['signature' => $signature]);
    }

    private function currentWallet(Request $request): ?Wallet
    {
        $id = $request->session()->get('wallet_id');

        return $id ? Wallet::find($id) : null;
    }
}
