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
    }

    /** Each guess must be funded by its own transaction, so never reuse a signature. */
    private function nextFeeSig(): string
    {
        return 'stub-fee-signature-'.(++$this->feeSigCounter);
    }

    public function test_wallet_without_holdings_cannot_guess(): void
    {
        // Below TZLA threshold AND no NFTs → not eligible to play.
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 0.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitGuess(1, 1, 'parchment')
            ->assertStatus(403)
            ->assertJson(['error' => 'not_eligible']);
    }

    public function test_nft_holder_can_play_without_tzla(): void
    {
        // NFT alone qualifies to play.
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 0.0, nftCount: 1));
        $this->connect();
        $this->seedWeek1();

        // 1 base + 1 NFT = 2 attempts; wrong then right.
        $this->submitGuess(1, 1, 'wrong')->assertOk();
        $this->submitGuess(1, 1, 'parchment')
            ->assertOk()
            ->assertJson(['solved_count' => 1, 'total_words' => 3]);
    }

    public function test_tzla_holder_gets_one_attempt_per_word(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitGuess(1, 1, 'wrong')
            ->assertOk()
            ->assertJson(['attempts_left' => 0, 'solved_count' => 0, 'is_correct' => false]);

        // Second attempt is refused before any payment is taken.
        $this->submitGuess(1, 1, 'parchment')
            ->assertStatus(409)
            ->assertJson(['error' => 'no_attempts_left']);
    }

    public function test_exhausted_attempts_do_not_consume_a_fee_payment(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->submitGuess(1, 1, 'wrong')->assertOk();

        $sig = 'unspent-signature';
        $this->submitGuess(1, 1, 'parchment', feeSig: $sig)->assertStatus(409);

        // The rejected guess must not have burned the player's payment.
        $this->assertDatabaseMissing('fee_payments', ['signature' => $sig]);
    }

    public function test_already_solved_word_is_refused_without_charging(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 2));
        $this->connect();
        $this->seedWeek1();

        $this->submitGuess(1, 1, 'parchment')->assertOk()->assertJson(['is_correct' => true]);

        $sig = 'signature-for-solved-word';
        $this->submitGuess(1, 1, 'parchment', feeSig: $sig)
            ->assertStatus(409)
            ->assertJson(['error' => 'already_solved']);

        $this->assertDatabaseMissing('fee_payments', ['signature' => $sig]);
    }

    public function test_fee_signature_cannot_be_replayed(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 3));
        $this->connect();
        $this->seedWeek1();

        $sig = 'one-payment-only';

        $this->submitGuess(1, 1, 'wrong', feeSig: $sig)->assertOk();

        // Same transaction, second guess: previously this bought unlimited plays.
        $this->submitGuess(1, 1, 'wrong-again', feeSig: $sig)
            ->assertStatus(402)
            ->assertJson(['error' => 'fee_signature_already_used']);

        // Also blocked across a different word.
        $this->submitGuess(1, 2, 'nope', feeSig: $sig)
            ->assertStatus(402)
            ->assertJson(['error' => 'fee_signature_already_used']);

        $this->assertDatabaseCount('fee_payments', 1);
    }

    public function test_fee_signature_cannot_be_reused_by_a_different_wallet(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->seedWeek1();

        $sig = 'shared-payment';

        $this->connect();
        $this->submitGuess(1, 1, 'wrong', feeSig: $sig)->assertOk();

        // A second wallet tries to piggyback on the first wallet's payment.
        $this->kp = new PhantomKeypair;
        $this->connect();
        $this->submitGuess(1, 1, 'wrong', feeSig: $sig)
            ->assertStatus(402)
            ->assertJson(['error' => 'fee_signature_already_used']);
    }

    public function test_nfts_add_attempts_and_correct_answer_is_normalized(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 3));
        $this->connect();
        $this->seedWeek1();

        // 4 attempts allowed; miss 3, then hit.
        $this->submitGuess(1, 2, 'nope')->assertOk();
        $this->submitGuess(1, 2, 'nope2')->assertOk();
        $this->submitGuess(1, 2, 'nope3')->assertOk();
        $this->submitGuess(1, 2, 'JOLLY-ROGER!')
            ->assertOk()
            ->assertJson(['solved_count' => 1]);
    }

    public function test_completing_all_words_writes_winner_log_and_notifies(): void
    {
        Queue::fake();
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));

        $logPath = storage_path('logs/winners.log');
        @unlink($logPath);

        $this->connect();
        $this->seedWeek1();

        $this->submitGuess(1, 1, 'parchment', username: 'capt_kidd', payout: str_repeat('B', 44))
            ->assertOk()
            ->assertJson(['solved_count' => 1, 'week_complete' => false]);
        $this->submitGuess(1, 2, 'jolly roger')
            ->assertOk()
            ->assertJson(['solved_count' => 2, 'week_complete' => false]);
        $this->submitGuess(1, 3, 'doubloon')
            ->assertOk()
            ->assertJson(['solved_count' => 3, 'total_words' => 3, 'week_complete' => true]);

        // 3 correct-word notifications + 1 week-complete notification.
        Queue::assertPushed(SendTelegramMessage::class, 4);

        $this->assertFileExists($logPath);
        $logContents = (string) file_get_contents($logPath);
        $this->assertStringContainsString('week_completed', $logContents);
        $this->assertStringContainsString('capt_kidd', $logContents);
        $this->assertStringContainsString($this->kp->address, $logContents);
        $this->assertStringContainsString(str_repeat('B', 44), $logContents);
    }

    public function test_guess_requires_wallet(): void
    {
        $this->seedWeek1();
        $this->submitGuess(1, 1, 'parchment')
            ->assertStatus(401)
            ->assertJson(['error' => 'wallet_not_connected']);
    }

    public function test_guess_on_locked_week_returns_403(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $week = Week::create([
            'number' => 2,
            'title' => 'Future',
            'starts_at' => now()->addYear(),
            'reward_description' => 'Locked booty',
        ]);
        Word::create(['week_id' => $week->id, 'position' => 1, 'answer_normalized' => 'x', 'hint' => 'h']);

        $this->submitGuess(2, 1, 'x')
            ->assertStatus(403)
            ->assertJson(['error' => 'week_locked']);
    }

    public function test_missing_fee_signature_rejected(): void
    {
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 0));
        $this->connect();
        $this->seedWeek1();

        $this->postJson('/api/weeks/1/words/1/guess', ['guess' => 'parchment'])
            ->assertStatus(422);
    }

    private function submitGuess(
        int $week,
        int $position,
        string $guess,
        ?string $feeSig = null,
        ?string $username = null,
        ?string $payout = null,
    ) {
        $payload = ['guess' => $guess, 'fee_signature' => $feeSig ?? $this->nextFeeSig()];
        if ($username !== null) {
            $payload['username'] = $username;
        }
        if ($payout !== null) {
            $payload['payout_address'] = $payout;
        }

        return $this->postJson("/api/weeks/{$week}/words/{$position}/guess", $payload);
    }

    private function connect(): void
    {
        $nonce = $this->postJson('/api/auth/nonce', ['address' => $this->kp->address])->json();
        $sig = $this->kp->signBase58($nonce['message']);
        $this->postJson('/api/auth/verify', [
            'address' => $this->kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $sig,
        ])->assertOk();
    }

    /** @return array{0: Week, 1: array<int, Word>} */
    private function seedWeek1(): array
    {
        $week = Week::create([
            'number' => 1,
            'title' => 'Bones',
            'starts_at' => now()->subDay(),
            'reward_description' => '10 TZLA',
        ]);
        $words = [
            Word::create(['week_id' => $week->id, 'position' => 1, 'answer_normalized' => 'parchment', 'hint' => 'Old and folded']),
            Word::create(['week_id' => $week->id, 'position' => 2, 'answer_normalized' => 'jollyroger', 'hint' => "Pirate's flag"]),
            Word::create(['week_id' => $week->id, 'position' => 3, 'answer_normalized' => 'doubloon', 'hint' => 'Golden coin']),
        ];

        return [$week, $words];
    }
}
