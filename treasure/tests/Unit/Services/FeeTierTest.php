<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Wallet;
use App\Services\Guess\FeeTier;
use Tests\TestCase;

final class FeeTierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'game.play_gate.tzla_mid_threshold' => 33,
            'game.submission_fees.golden_ticket_sol' => 0.03,
            'game.submission_fees.mid_sol' => 0.06,
            'game.submission_fees.standard_sol' => 0.09,
        ]);
    }

    public function test_golden_ticket_beats_everything(): void
    {
        $wallet = new Wallet([
            'tzla_balance_cached' => 100,
            'nft_count_cached' => 5,
            'golden_ticket_count_cached' => 1,
            'staked_amount_cached' => 50,
        ]);

        $this->assertSame(0.03, (new FeeTier)->amountSol($wallet));
    }

    public function test_nft_gets_mid_tier(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 0, 'nft_count_cached' => 1]);
        $this->assertSame(0.06, (new FeeTier)->amountSol($wallet));
    }

    public function test_staked_gets_mid_tier(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 0, 'nft_count_cached' => 0, 'staked_amount_cached' => 1]);
        $this->assertSame(0.06, (new FeeTier)->amountSol($wallet));
    }

    public function test_high_tzla_without_nft_or_stake_pays_standard(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 40, 'nft_count_cached' => 0]);
        $this->assertSame(0.09, (new FeeTier)->amountSol($wallet));
        $this->assertSame('TZLA holder', (new FeeTier)->label($wallet));
    }

    public function test_low_tzla_gets_standard_tier(): void
    {
        $wallet = new Wallet(['tzla_balance_cached' => 12, 'nft_count_cached' => 0]);
        $this->assertSame(0.09, (new FeeTier)->amountSol($wallet));
    }
}
