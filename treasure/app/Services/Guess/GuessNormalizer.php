<?php

declare(strict_types=1);

namespace App\Services\Guess;

final class GuessNormalizer
{
    public function normalize(string $input): string
    {
        $lower = mb_strtolower(trim($input));

        return (string) preg_replace('/[^a-z0-9]+/u', '', $lower);
    }
}
