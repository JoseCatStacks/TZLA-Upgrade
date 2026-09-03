<?php

declare(strict_types=1);

namespace App\Services\Wallet;

final class Base58
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function decode(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $indexes = array_flip(str_split(self::ALPHABET));
        $bytes = [0];
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if (! isset($indexes[$char])) {
                throw new \InvalidArgumentException("Invalid base58 character: {$char}");
            }
            $carry = $indexes[$char];
            for ($j = 0; $j < count($bytes); $j++) {
                $carry += $bytes[$j] * 58;
                $bytes[$j] = $carry & 0xFF;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xFF;
                $carry >>= 8;
            }
        }

        $leadingZeros = 0;
        for ($i = 0; $i < $length && $input[$i] === '1'; $i++) {
            $leadingZeros++;
        }

        $bytes = array_reverse($bytes);
        $binary = '';
        foreach ($bytes as $b) {
            $binary .= chr($b);
        }

        return str_repeat("\x00", $leadingZeros).ltrim($binary, "\x00");
    }
}
