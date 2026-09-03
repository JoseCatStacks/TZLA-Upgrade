<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Wallet\Base58;
use PHPUnit\Framework\TestCase;

final class Base58Test extends TestCase
{
    public function test_decodes_all_ones_to_all_zero_bytes(): void
    {
        $bytes = Base58::decode(str_repeat('1', 32));
        $this->assertSame(str_repeat("\x00", 32), $bytes);
    }

    public function test_roundtrip_random_pubkeys_have_32_bytes(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $pk = sodium_crypto_sign_publickey($keypair);
        $addr = self::base58Encode($pk);

        $decoded = Base58::decode($addr);
        $this->assertSame(32, strlen($decoded));
        $this->assertSame($pk, $decoded);
    }

    public function test_invalid_character_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Base58::decode('0OIl'); // 0, O, I, l are excluded from base58
    }

    private static function base58Encode(string $bytes): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
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
            $out .= $alphabet[$digits[$k]];
        }

        return $out;
    }
}
