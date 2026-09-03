<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Solana\FeeVerifier;
use App\Services\Solana\HeliusFeeVerifier;
use App\Services\Solana\HoldingsVerifier;
use App\Services\Solana\StubFeeVerifier;
use RuntimeException;
use Tests\TestCase;

final class SolanaProviderBindingTest extends TestCase
{
    private function rebind(string $provider, string $environment): void
    {
        config(['solana.provider' => $provider]);
        $this->app->detectEnvironment(fn (): string => $environment);
        $this->app->forgetInstance(FeeVerifier::class);
        $this->app->forgetInstance(HoldingsVerifier::class);
    }

    public function test_stub_is_allowed_locally(): void
    {
        $this->rebind('stub', 'local');

        $this->assertInstanceOf(StubFeeVerifier::class, $this->app->make(FeeVerifier::class));
    }

    public function test_stub_fee_verifier_is_refused_in_production(): void
    {
        $this->rebind('stub', 'production');

        // The stub accepts any fee signature, so falling back to it in
        // production would hand out free guesses.
        $this->expectException(RuntimeException::class);
        $this->app->make(FeeVerifier::class);
    }

    public function test_stub_holdings_verifier_is_refused_in_production(): void
    {
        $this->rebind('stub', 'production');

        $this->expectException(RuntimeException::class);
        $this->app->make(HoldingsVerifier::class);
    }

    public function test_unknown_provider_fails_loudly_instead_of_falling_back(): void
    {
        // A typo used to silently select the stub.
        $this->rebind('helius-mainnet', 'production');

        $this->expectException(RuntimeException::class);
        $this->app->make(FeeVerifier::class);
    }

    public function test_helius_provider_resolves_the_real_verifier(): void
    {
        $this->rebind('helius', 'production');

        $this->assertInstanceOf(HeliusFeeVerifier::class, $this->app->make(FeeVerifier::class));
    }
}
