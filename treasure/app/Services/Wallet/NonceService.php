<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Models\AuthNonce;
use Illuminate\Support\Str;

final class NonceService
{
    public function __construct(
        private readonly int $ttlSeconds = 300,
        private readonly string $domain = 'localhost',
    ) {}

    public function issue(string $address): AuthNonce
    {
        return AuthNonce::create([
            'wallet_address' => $address,
            'nonce' => Str::random(32),
            'expires_at' => now()->addSeconds($this->ttlSeconds),
        ]);
    }

    public function messageFor(AuthNonce $nonce): string
    {
        return sprintf(
            "%s wants you to sign in with your Solana account:\n%s\n\nSign in to TZLA Treasure Hunt.\n\nNonce: %s\nExpires: %s",
            $this->domain,
            $nonce->wallet_address,
            $nonce->nonce,
            $nonce->expires_at->toIso8601String(),
        );
    }

    public function consume(string $address, string $nonce): ?AuthNonce
    {
        $row = AuthNonce::where('wallet_address', $address)
            ->where('nonce', $nonce)
            ->first();

        if ($row === null || $row->isExpired() || $row->isUsed()) {
            return null;
        }

        $row->forceFill(['used_at' => now()])->save();

        return $row;
    }

    public function purgeExpired(): int
    {
        return AuthNonce::where('expires_at', '<', now())->delete();
    }
}
