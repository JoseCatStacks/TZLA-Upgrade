<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\Guess\AttemptPolicy;
use App\Services\Solana\WalletHoldingsSync;
use App\Services\Wallet\NonceService;
use App\Services\Wallet\SignatureVerifier;
use App\Services\Wallet\XmrAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class AuthController extends Controller
{
    public function __construct(
        private readonly NonceService $nonces,
        private readonly SignatureVerifier $signatures,
        private readonly WalletHoldingsSync $holdingsSync,
        private readonly AttemptPolicy $policy,
    ) {}

    public function nonce(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'min:32', 'max:64'],
        ]);

        $nonce = $this->nonces->issue($data['address']);

        return response()->json([
            'nonce' => $nonce->nonce,
            'message' => $this->nonces->messageFor($nonce),
            'expires_at' => $nonce->expires_at->toIso8601String(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'min:32', 'max:64'],
            'nonce' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $nonce = $this->nonces->consume($data['address'], $data['nonce']);
        if ($nonce === null) {
            return response()->json(['error' => 'invalid_or_expired_nonce'], 422);
        }

        $message = $this->nonces->messageFor($nonce);
        if (! $this->signatures->verify($data['address'], $message, $data['signature'])) {
            return response()->json(['error' => 'bad_signature'], 422);
        }

        $wallet = Wallet::firstOrCreate(
            ['address' => $data['address']],
            ['first_connected_at' => now(), 'last_seen_at' => now()],
        );
        $wallet->forceFill(['last_seen_at' => now()])->save();

        Cache::forget("holdings:{$wallet->address}");
        $this->holdingsSync->refresh($wallet);

        $request->session()->regenerate();
        $request->session()->put('wallet_id', $wallet->id);

        return response()->json($this->walletPayload($wallet));
    }

    public function profile(Request $request): JsonResponse
    {
        $walletId = $request->session()->get('wallet_id');
        $wallet = $walletId ? Wallet::find($walletId) : null;
        if ($wallet === null) {
            return response()->json(['error' => 'wallet_not_connected'], 401);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'min:2', 'max:64'],
            'payout_address' => ['required', 'string', 'max:110', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! XmrAddress::isValid($value)) {
                    $fail('Enter a valid Monero (XMR) receiving address.');
                }
            }],
        ]);

        $wallet->forceFill([
            'username' => trim($data['username']),
            'payout_address' => trim($data['payout_address']),
        ])->save();

        return response()->json($this->walletPayload($wallet));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('wallet_id');
        $request->session()->regenerate(true);

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $walletId = $request->session()->get('wallet_id');
        if (! $walletId) {
            return response()->json(['wallet' => null]);
        }

        $wallet = Wallet::find($walletId);
        if (! $wallet) {
            return response()->json(['wallet' => null]);
        }

        $this->holdingsSync->refresh($wallet);

        return response()->json($this->walletPayload($wallet));
    }

    /** @return array<string, mixed> */
    private function walletPayload(Wallet $wallet): array
    {
        return [
            'wallet' => [
                'address'             => $wallet->address,
                'short'               => $wallet->shortAddress(),
                'username'            => $wallet->username,
                'payout_address'      => $wallet->payout_address,
                'profile_complete'    => $wallet->hasPayoutProfile(),
                'holds_tzla'          => $wallet->holdsTzla(),
                'has_staked'          => $wallet->hasStaked(),
                'nft_count'           => $wallet->nftCount(),
                'golden_ticket_count' => $wallet->goldenTicketCount(),
                'tzla_balance'        => $wallet->tzlaBalance(),
                'staked_amount'       => $wallet->stakedAmount(),
                'can_play'            => $wallet->canPlay(),
            ],
            'attempts_per_week' => $this->policy->attemptsAllowedPerWeek($wallet),
            'attempts_per_word' => $this->policy->attemptsAllowedPerWeek($wallet),
        ];
    }
}
