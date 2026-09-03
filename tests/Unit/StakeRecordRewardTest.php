<?php

namespace Tests\Unit;

use App\Models\StakeRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StakeRecordRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_day_base_tier_matches_on_chain_formula(): void
    {
        $record = StakeRecord::create([
            'wallet' => 'TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3',
            'amount_raw' => '100000000000', // 100 TZLA
            'nft_tier' => 0,
            'staked_at' => now()->subDay(),
            'stake_tx' => 'unit-test-sig',
        ]);

        // 100e9 * 69 * 86400 / (100000 * 86400) = 69000000 raw = 0.069 TZLA
        $this->assertSame('69000000', $record->predictedYieldRaw());
        $this->assertSame('0.069000000', $record->predictedYieldTokens());
    }

    public function test_golden_tier_is_higher_than_base(): void
    {
        $base = StakeRecord::create([
            'wallet' => '11111111111111111111111111111111',
            'amount_raw' => '1000000000',
            'nft_tier' => 0,
            'staked_at' => now()->subDays(10),
            'stake_tx' => 'base-sig',
        ]);

        $golden = StakeRecord::create([
            'wallet' => '22222222222222222222222222222222',
            'amount_raw' => '1000000000',
            'nft_tier' => 3,
            'staked_at' => now()->subDays(10),
            'stake_tx' => 'golden-sig',
        ]);

        $this->assertTrue(bccomp($golden->predictedYieldRaw(), $base->predictedYieldRaw()) > 0);
    }

    public function test_closed_position_projects_zero(): void
    {
        $record = StakeRecord::create([
            'wallet' => 'TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3',
            'amount_raw' => '1000000000',
            'nft_tier' => 3,
            'staked_at' => now()->subDays(10),
            'unstaked_at' => now()->subDay(),
            'stake_tx' => 'closed-sig',
        ]);

        $this->assertSame('0', $record->projectedRewardRaw(7));
    }
}
