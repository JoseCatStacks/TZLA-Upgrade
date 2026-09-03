<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\Solana\BlockhashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes the values the browser needs to build a fee payment. Everything here
 * is public information; the Helius key stays server-side.
 */
final class GameConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallet = $this->currentWallet($request);

        $standard = (float) config('game.submission_fees.standard_sol', 0.06);
        $golden   = (float) config('game.submission_fees.golden_ticket_sol', 0.03);

        return response()->json([
            'treasury_address' => config('game.submission_fees.treasury_address'),
            'network'          => config('solana.network'),
            'fees'             => [
                'standard_sol'      => $standard,
                'golden_ticket_sol' => $golden,
            ],
            // In local development the stub verifier accepts any signature, so the
            // browser skips the real transfer instead of demanding devnet SOL.
            'payments_enabled' => strtolower((string) config('solana.provider')) === 'helius',
            'your_fee_sol'     => $wallet && $wallet->holdsGoldenTicket() ? $golden : $standard,
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

    private function currentWallet(Request $request): ?Wallet
    {
        $id = $request->session()->get('wallet_id');

        return $id ? Wallet::find($id) : null;
    }
}
