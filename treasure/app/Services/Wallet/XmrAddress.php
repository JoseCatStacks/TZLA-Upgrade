<?php

declare(strict_types=1);

namespace App\Services\Wallet;

final class XmrAddress
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function isValid(string $address): bool
    {
        $address = trim($address);
        $class = '['.self::ALPHABET.']';

        // Primary / subaddress (95 chars, 4… or 8…)
        if (preg_match('/^[48]'.$class.'{94}$/', $address) === 1) {
            return true;
        }

        // Integrated address (106 chars, 4…)
        return preg_match('/^4'.$class.'{105}$/', $address) === 1;
    }
}
