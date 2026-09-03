<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendTelegramMessage;
use App\Models\Week;
use App\Models\Word;
use App\Services\Solana\HoldingsVerifier;
use App\Services\Solana\StubHoldingsVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Support\PhantomKeypair;
use Tests\TestCase;

final class GuessFlowTest extends TestCase
{
    use RefreshDatabase;

    private PhantomKeypair $kp;

    private int $feeSigCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kp = new PhantomKeypair;
        $this->feeSigCounter = 0;
        config([
            'game.play_gate.tzla_threshold' => 9.63,
            'game.play_gate.tzla_mid_threshold' => 33,
            'game.submission_fees.standard_sol' => 0.09,
            'game.submission_fees.mid_sol' => 0.06,
            'game.submission_fees.golden_ticket_sol' => 0.03,
        ]);
    }

    private function nextFeeSig(): string
    {
        return 'stub-fee-signature-'.(++$this->feeSigCounter);
    }

    public function test_wallet_without_holdings_cannot_guess(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 0.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'])
            ->assertStatus(403)
            ->assertJson(['error' => 'not_eligible']);
    }

    public function test_incomplete_answers_refused_without_charging(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $sig = 'unspent-signature';
        $this->submitBundle(1, ['parchment', 'jollyroger'], feeSig: $sig)
            ->assertStatus(422)
            ->assertJson(['error' => 'incomplete_answers']);

        $this->assertDatabaseMissing('fee_payments', ['signature' => $sig]);
    }

    public function test_bundle_returns_count_not_which_words(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 1));
        $this->connect();
        $this->seedWeek1();

        $res = $this->submitBundle(1, ['parchment', 'wrong', 'doubloon'])
            ->assertOk()
            ->assertJson([
                'correct_count' => 2,
                'total_words' => 3,
                'is_complete' => false,
            ])
            ->json();

        $this->assertArrayNotHasKey('words', $res);
        $this->assertArrayNotHasKey('is_correct', $res);
    }

    public function test_week_show_stays_blind_until_complete(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 2));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['parchment', 'wrong', 'wrong'])->assertOk();

        $show = $this->getJson('/api/weeks/1')->assertOk()->json();
        $this->assertFalse($show['week_complete']);
        foreach ($show['words'] as $word) {
            $this->assertArrayNotHasKey('is_solved', $word);
            $this->assertArrayNotHasKey('solved_answer', $word);
            $this->assertArrayHasKey('hint', $word);
        }
    }

    public function test_tzla_holder_gets_one_bundle_attempt(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['wrong', 'wrong', 'wrong'])
            ->assertOk()
            ->assertJson(['attempts_left' => 0, 'correct_count' => 0]);

        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'])
            ->assertStatus(409)
            ->assertJson(['error' => 'no_attempts_left']);
    }

    public function test_exhausted_attempts_do_not_consume_a_fee_payment(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['wrong', 'wrong', 'wrong'])->assertOk();

        $sig = 'unspent-signature';
        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'], feeSig: $sig)
            ->assertStatus(409);

        $this->assertDatabaseMissing('fee_payments', ['signature' => $sig]);
    }

    public function test_fee_signature_cannot_be_replayed(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 3));
        $this->connect();
        $this->seedWeek1();

        $sig = 'replay-me';
        $this->submitBundle(1, ['wrong', 'wrong', 'wrong'], feeSig: $sig)->assertOk();
        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'], feeSig: $sig)
            ->assertStatus(402)
            ->assertJson(['error' => 'fee_signature_already_used']);
    }

    public function test_fee_signature_cannot_be_reused_by_a_different_wallet(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $sig = 'shared-sig';
        $this->submitBundle(1, ['wrong', 'wrong', 'wrong'], feeSig: $sig)->assertOk();

        $this->kp = new PhantomKeypair;
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 3));
        $this->connect();

        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'], feeSig: $sig)
            ->assertStatus(402)
            ->assertJson(['error' => 'fee_signature_already_used']);
    }

    public function test_full_clear_writes_winner_log_and_notifies(): void
    {
        Queue::fake();
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'])
            ->assertOk()
            ->assertJson([
                'correct_count' => 3,
                'total_words' => 3,
                'is_complete' => true,
                'week_complete' => true,
            ]);

        $show = $this->getJson('/api/weeks/1')->assertOk()->json();
        $this->assertTrue($show['week_complete']);
        $this->assertSame('parchment', $show['words'][0]['solved_answer']);

        $logContents = file_get_contents(storage_path('logs/winners.log'));
        $this->assertStringContainsString('week_completed', $logContents);

        Queue::assertPushed(SendTelegramMessage::class);
    }

    public function test_already_completed_refused_without_charging(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 2));
        $this->connect();
        $this->seedWeek1();

        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'])->assertOk();

        $sig = 'after-win';
        $this->submitBundle(1, ['parchment', 'jollyroger', 'doubloon'], feeSig: $sig)
            ->assertStatus(409)
            ->assertJson(['error' => 'already_completed']);

        $this->assertDatabaseMissing('fee_payments', ['signature' => $sig]);
    }

    public function test_guess_requires_wallet(): void
    {
        $this->seedWeek1();
        $this->postJson('/api/weeks/1/bundle', [
            'answers' => ['a', 'b', 'c'],
            'fee_signature' => 'x',
        ])->assertStatus(401);
    }

    public function test_guess_on_locked_week_returns_403(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();

        $week = Week::create([
            'number' => 9,
            'title' => 'Future',
            'active' => true,
            'starts_at' => now()->addWeek(),
        ]);
        Word::create(['week_id' => $week->id, 'position' => 1, 'answer' => 'a', 'answer_normalized' => 'a', 'hint' => 'h']);
        Word::create(['week_id' => $week->id, 'position' => 2, 'answer' => 'b', 'answer_normalized' => 'b', 'hint' => 'h']);
        Word::create(['week_id' => $week->id, 'position' => 3, 'answer' => 'c', 'answer_normalized' => 'c', 'hint' => 'h']);

        $this->submitBundle(9, ['a', 'b', 'c'])->assertStatus(403);
    }

    public function test_missing_fee_signature_rejected(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->postJson('/api/weeks/1/bundle', [
            'answers' => ['parchment', 'jollyroger', 'doubloon'],
        ])->assertStatus(422);
    }

    public function test_mid_fee_tier_for_high_tzla_balance(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 40.0, nftCount: 0));
        $this->connect();

        $this->getJson('/api/game-config')
            ->assertOk()
            ->assertJsonPath('your_fee_sol', 0.06)
            ->assertJsonPath('your_fee_tier', '33+ TZLA');
    }

    private function connect(): void
    {
        $address = $this->kp->address;
        $nonceRes = $this->postJson('/api/auth/nonce', ['address' => $address])->assertOk()->json();
        $sig = $this->kp->signBase58($nonceRes['message']);
        $this->postJson('/api/auth/verify', [
            'address' => $address,
            'nonce' => $nonceRes['nonce'],
            'signature' => $sig,
        ])->assertOk();
    }

    private function seedWeek1(): void
    {
        $week = Week::create([
            'number' => 1,
            'title' => 'Week 1',
            'active' => true,
            'starts_at' => now()->subHour(),
            'reward_description' => 'Booty',
        ]);
        foreach ([
            1 => ['parchment', 'Old paper'],
            2 => ['jollyroger', 'Black flag'],
            3 => ['doubloon', 'Gold coin'],
        ] as $pos => [$answer, $hint]) {
            Word::create([
                'week_id' => $week->id,
                'position' => $pos,
                'answer' => $answer,
                'answer_normalized' => $answer,
                'hint' => $hint,
            ]);
        }
    }

    /** @param  list<string>  $answers */
    private function submitBundle(int $week, array $answers, ?string $feeSig = null)
    {
        return $this->postJson("/api/weeks/{$week}/bundle", [
            'answers' => $answers,
            'fee_signature' => $feeSig ?? $this->nextFeeSig(),
        ]);
    }
}
