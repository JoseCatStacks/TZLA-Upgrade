<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Wallet;
use App\Services\Guess\AttemptPolicy;
use Tests\TestCase;

final class AttemptPolicyTest extends TestCase
{
    public function test_no_holdings_means_zero_attempts(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 0, 'nft_count_cached' => 0]);
        $this->assertSame(0, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }

    public function test_nft_holder_can_play_without_tzla(): void
    {
        // NFT alone qualifies to play — no TZLA required.
        $wallet = new Wallet(['tzla_balance_cached' => 0, 'nft_count_cached' => 4]);
        $this->assertSame(5, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }

    public function test_attempts_are_capped_regardless_of_nft_count(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 0, 'nft_count_cached' => 500]);
        $this->assertSame(
            (int) config('game.attempts.max_per_word'),
            (new AttemptPolicy)->attemptsAllowedPerWord($wallet),
        );
    }

    public function test_tzla_below_threshold_and_no_nft_gives_zero(): void
    {
        // Threshold is 9.63 TZLA; 9.0 is not enough on its own.
        $wallet = new Wallet(['tzla_balance_cached' => 9.0, 'nft_count_cached' => 0]);
        $this->assertSame(0, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }

    public function test_tzla_at_threshold_gives_one_attempt(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 9.63, 'nft_count_cached' => 0]);
        $this->assertSame(1, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }

    public function test_each_nft_adds_one_attempt(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 10, 'nft_count_cached' => 3]);
        $this->assertSame(4, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }

    public function test_null_nft_count_treats_as_zero(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 10, 'nft_count_cached' => null]);
        $this->assertSame(1, (new AttemptPolicy)->attemptsAllowedPerWord($wallet));
    }
}
