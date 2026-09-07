<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GameConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_config_exposes_treasury_and_fees(): void
    {
        config([
            'game.submission_fees.treasury_address' => 'TreasuryAddress11111111111111111111111111',
            'game.submission_fees.standard_sol' => 0.09,
            'game.submission_fees.mid_sol' => 0.06,
            'game.submission_fees.golden_ticket_sol' => 0.03,
        ]);

        $this->getJson('/api/game-config')
            ->assertOk()
            ->assertJson([
                'treasury_address' => 'TreasuryAddress11111111111111111111111111',
                'fees' => [
                    'standard_sol' => 0.09,
                    'mid_sol' => 0.06,
                    'golden_ticket_sol' => 0.03,
                ],
                'your_fee_sol' => 0.09,
            ]);
    }

    public function test_game_config_never_exposes_the_helius_key(): void
    {
        config(['solana.helius.api_key' => 'super-secret-key']);

        $body = $this->getJson('/api/game-config')->assertOk()->getContent();

        $this->assertStringNotContainsString('super-secret-key', $body);
    }

    public function test_payments_are_disabled_while_the_stub_provider_is_active(): void
    {
        config(['solana.provider' => 'stub']);

        $this->getJson('/api/game-config')
            ->assertOk()
            ->assertJson(['payments_enabled' => false]);
    }

    public function test_game_config_exposes_rollout_controls(): void
    {
        config([
            'game.weeks.max_playable' => 1,
            'game.weeks.unlimited_attempts' => [1],
        ]);

        $this->getJson('/api/game-config')
            ->assertOk()
            ->assertJson([
                'max_playable_week' => 1,
                'unlimited_attempt_weeks' => [1],
                'prizes' => [
                    'paid_places' => 5,
                    'xmr' => [
                        1 => 0.6,
                        2 => 0.3,
                        3 => 0.2,
                        4 => 0.1,
                        5 => 0.1,
                    ],
                ],
            ]);
    }

    public function test_inactive_weeks_are_hidden_from_the_public_list(): void
    {
        Week::create([
            'number' => 1,
            'title' => 'Live Week',
            'starts_at' => now()->subDay(),
            'active' => true,
        ]);
        Week::create([
            'number' => 2,
            'title' => 'Unannounced Draft',
            'starts_at' => now()->subDay(),
            'reward_description' => 'Secret prize nobody should see yet',
            'active' => false,
        ]);

        $response = $this->getJson('/api/weeks')->assertOk();

        $response->assertJsonCount(1, 'weeks');
        $response->assertJsonPath('weeks.0.number', 1);
        $this->assertStringNotContainsString('Unannounced Draft', $response->getContent());
        $this->assertStringNotContainsString('Secret prize', $response->getContent());
    }
}
