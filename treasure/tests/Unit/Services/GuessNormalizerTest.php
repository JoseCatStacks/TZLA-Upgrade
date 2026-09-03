<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Guess\GuessNormalizer;
use PHPUnit\Framework\TestCase;

final class GuessNormalizerTest extends TestCase
{
    private GuessNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new GuessNormalizer;
    }

    public function test_lowercases_and_trims(): void
    {
        $this->assertSame('parchment', $this->normalizer->normalize('  Parchment  '));
        $this->assertSame('parchment', $this->normalizer->normalize('PARCHMENT'));
    }

    public function test_strips_punctuation_and_spaces(): void
    {
        $this->assertSame('jollyroger', $this->normalizer->normalize('jolly-roger'));
        $this->assertSame('jollyroger', $this->normalizer->normalize('jolly roger'));
        $this->assertSame('doubloon', $this->normalizer->normalize('Doubloon!'));
        $this->assertSame('doubloon', $this->normalizer->normalize("Doubl'oon"));
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame('', $this->normalizer->normalize(''));
        $this->assertSame('', $this->normalizer->normalize('   '));
        $this->assertSame('', $this->normalizer->normalize('---'));
    }

    public function test_alphanumerics_preserved(): void
    {
        $this->assertSame('ab12', $this->normalizer->normalize('ab-12'));
        $this->assertSame('ab12', $this->normalizer->normalize(' AB 12 '));
    }
}
