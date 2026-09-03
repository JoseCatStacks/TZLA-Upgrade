<?php

namespace Tests\Feature;

use App\Jobs\VerifyAndRecordStake;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StakingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_record_stake_rejects_invalid_wallet(): void
    {
        $this->postJson('/api/staking/record-stake', [
            'wallet' => 'not-a-wallet',
            'amount_raw' => '1',
            'nft_tier' => 0,
            'stake_tx' => 'sig',
        ])->assertStatus(422);
    }

    public function test_record_stake_accepts_valid_payload_without_helius(): void
    {
        Bus::fake();

        $this->postJson('/api/staking/record-stake', [
            'wallet' => 'TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3',
            'amount_raw' => '1000000000',
            'nft_tier' => 0,
            'stake_tx' => str_repeat('A', 64),
        ])->assertOk()->assertJson(['ok' => true]);

        Bus::assertDispatched(VerifyAndRecordStake::class);
    }

    public function test_pool_stats_degrades_without_oracle_data(): void
    {
        Http::fake([
            'lite-api.jup.ag/*' => Http::response(['error' => 'no'], 503),
        ]);

        $this->getJson('/api/staking/pool-stats')
            ->assertOk()
            ->assertJsonStructure(['distributed_raw', 'distributed_tokens', 'price_usd', 'as_of']);
    }

    public function test_rewards_summary_for_unknown_wallet_is_empty(): void
    {
        $this->getJson('/api/staking/rewards/TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3')
            ->assertOk()
            ->assertJsonPath('open_positions', 0)
            ->assertJsonPath('total_staked_raw', '0');
    }
}
