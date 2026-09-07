<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthNonce;
use App\Services\Solana\HoldingsVerifier;
use App\Services\Solana\StubHoldingsVerifier;
use App\Services\Wallet\NonceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\PhantomKeypair;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Threshold is 9.63 TZLA; 10.0 clears it.
        $this->app->instance(HoldingsVerifier::class, new StubHoldingsVerifier(tzlaBalance: 10.0, nftCount: 2));
    }

    public function test_nonce_issuance_and_signature_verify_completes_login(): void
    {
        $kp = new PhantomKeypair;

        $nonceRes = $this->postJson('/api/auth/nonce', ['address' => $kp->address])
            ->assertOk()
            ->json();
        $this->assertNotEmpty($nonceRes['nonce']);
        $this->assertNotEmpty($nonceRes['message']);

        $signature = $kp->signBase58($nonceRes['message']);

        $verifyRes = $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonceRes['nonce'],
            'signature' => $signature,
        ])->assertOk()->json();

        $this->assertSame($kp->address, $verifyRes['wallet']['address']);
        $this->assertTrue($verifyRes['wallet']['holds_tzla']);
        $this->assertSame(2, $verifyRes['wallet']['nft_count']);
        $this->assertSame(3, $verifyRes['attempts_per_word']); // 1 (tzla) + 2 (nfts)
        $this->assertFalse($verifyRes['wallet']['profile_complete']);
    }

    public function test_profile_saves_username_and_xmr(): void
    {
        $kp = new PhantomKeypair;
        $nonce = $this->postJson('/api/auth/nonce', ['address' => $kp->address])->json();
        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $kp->signBase58($nonce['message']),
        ])->assertOk();

        $xmr = '4'.str_repeat('A', 94);
        $this->postJson('/api/auth/profile', [
            'username' => 'jose-watch',
            'payout_address' => $xmr,
        ])->assertOk()
            ->assertJsonPath('wallet.username', 'jose-watch')
            ->assertJsonPath('wallet.payout_address', $xmr)
            ->assertJsonPath('wallet.profile_complete', true);
    }

    public function test_profile_rejects_invalid_xmr(): void
    {
        $kp = new PhantomKeypair;
        $nonce = $this->postJson('/api/auth/nonce', ['address' => $kp->address])->json();
        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $kp->signBase58($nonce['message']),
        ])->assertOk();

        $this->postJson('/api/auth/profile', [
            'username' => 'jose-watch',
            'payout_address' => 'not-an-xmr-address',
        ])->assertStatus(422);
    }

    public function test_profile_requires_connected_wallet(): void
    {
        $this->postJson('/api/auth/profile', [
            'username' => 'ghost',
            'payout_address' => '4'.str_repeat('A', 94),
        ])->assertStatus(401);
    }

    public function test_replay_of_same_nonce_rejected(): void
    {
        $kp = new PhantomKeypair;
        $nonce = $this->postJson('/api/auth/nonce', ['address' => $kp->address])->json();
        $signature = $kp->signBase58($nonce['message']);

        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $signature,
        ])->assertOk();

        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $signature,
        ])->assertStatus(422)->assertJson(['error' => 'invalid_or_expired_nonce']);
    }

    public function test_bad_signature_rejected(): void
    {
        $kp = new PhantomKeypair;
        $nonce = $this->postJson('/api/auth/nonce', ['address' => $kp->address])->json();
        $otherKp = new PhantomKeypair;
        $signature = $otherKp->signBase58($nonce['message']);

        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => $nonce['nonce'],
            'signature' => $signature,
        ])->assertStatus(422)->assertJson(['error' => 'bad_signature']);
    }

    public function test_expired_nonce_rejected(): void
    {
        $kp = new PhantomKeypair;
        $nonce = AuthNonce::create([
            'wallet_address' => $kp->address,
            'nonce' => 'expiredNonce123',
            'expires_at' => now()->subMinute(),
        ]);
        $message = app(NonceService::class)->messageFor($nonce);
        $signature = $kp->signBase58($message);

        $this->postJson('/api/auth/verify', [
            'address' => $kp->address,
            'nonce' => 'expiredNonce123',
            'signature' => $signature,
        ])->assertStatus(422)->assertJson(['error' => 'invalid_or_expired_nonce']);
    }
}
