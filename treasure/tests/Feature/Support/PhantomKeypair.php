<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

final class PhantomKeypair
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public string $address;

    public string $secret;

    public string $publicKey;

    public function __construct()
    {
        $keypair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($keypair);
        $this->secret = sodium_crypto_sign_secretkey($keypair);
        $this->address = self::base58Encode($this->publicKey);
    }

    public function signBase58(string $message): string
    {
        $sig = sodium_crypto_sign_detached($message, $this->secret);

        return self::base58Encode($sig);
    }

    public static function base58Encode(string $bytes): string
    {
        $zeros = 0;
        $length = strlen($bytes);
        while ($zeros < $length && $bytes[$zeros] === "\x00") {
            $zeros++;
        }
        $digits = [0];
        for ($i = $zeros; $i < $length; $i++) {
            $carry = ord($bytes[$i]);
            for ($j = 0; $j < count($digits); $j++) {
                $carry += $digits[$j] << 8;
                $digits[$j] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }
        $out = str_repeat('1', $zeros);
        for ($k = count($digits) - 1; $k >= 0; $k--) {
            $out .= self::ALPHABET[$digits[$k]];
        }

        return $out;
    }
}
