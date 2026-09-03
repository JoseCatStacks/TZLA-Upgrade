<?php

declare(strict_types=1);

namespace App\Services\Wallet;

final class SignatureVerifier
{
    public function verify(string $address, string $message, string $signatureBase58): bool
    {
        try {
            $publicKey = Base58::decode($address);
            $signature = Base58::decode($signatureBase58);
        } catch (\InvalidArgumentException) {
            return false;
        }

        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        } catch (\SodiumException) {
            return false;
        }
    }
}
