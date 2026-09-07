<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Wallet;
use App\Models\Week;
use App\Models\WeekCompletion;
use App\Services\Guess\PrizeLadder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrizeLadderTest extends TestCase
{
    use RefreshDatabase;

    public function test_places_and_prizes_follow_finish_order(): void
    {
        $week = Week::create(['number' => 1, 'active' => true, 'starts_at' => now()->subHour()]);
        $first = Wallet::create(['address' => 'wallet-one-11111111111111111111111111111']);
        $second = Wallet::create(['address' => 'wallet-two-22222222222222222222222222222']);

        $c1 = WeekCompletion::create(['wallet_id' => $first->id, 'week_id' => $week->id, 'completed_at' => now()]);
        $c2 = WeekCompletion::create(['wallet_id' => $second->id, 'week_id' => $week->id, 'completed_at' => now()]);

        $ladder = new PrizeLadder;
        $this->assertSame(1, $ladder->placeFor($c1));
        $this->assertSame(2, $ladder->placeFor($c2));
        $this->assertSame(0.6, $ladder->prizeXmr(1));
        $this->assertSame(0.3, $ladder->prizeXmr(2));
        $this->assertNull($ladder->prizeXmr(6));
    }

    public function test_telegram_card_includes_username_and_xmr(): void
    {
        $wallet = new Wallet([
            'address' => 'Sol111111111111111111111111111111111111111',
            'username' => 'jose-test',
            'payout_address' => '4'.str_repeat('A', 94),
        ]);
        $week = new Week(['number' => 1]);
        $message = (new PrizeLadder)->telegramMessage($wallet, $week, 1, 0.6);

        $this->assertStringContainsString('Week 1 clear — place 1 / 0.6 XMR', $message);
        $this->assertStringContainsString('username: jose-test', $message);
        $this->assertStringContainsString('xmr: 4AAAAAAAA', $message);
    }
}
