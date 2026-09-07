<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Guess\SpamGuard;
use App\Services\Notification\TelegramNotifier;
use App\Services\Solana\BlockhashService;
use App\Services\Solana\FeeVerifier;
use App\Services\Solana\HeliusFeeVerifier;
use App\Services\Solana\HeliusHoldingsVerifier;
use App\Services\Solana\HoldingsVerifier;
use App\Services\Solana\StakePositionReader;
use App\Services\Solana\StubFeeVerifier;
use App\Services\Solana\StubHoldingsVerifier;
use App\Services\Wallet\NonceService;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StakePositionReader::class, fn ($app): StakePositionReader => new StakePositionReader(
            apiKey: (string) config('solana.helius.api_key'),
            rpcUrl: (string) config('solana.helius.rpc_url'),
            programId: (string) config('solana.staking.program_id'),
            poolAddress: (string) config('solana.staking.pool_address'),
            commitment: (string) config('solana.commitment', 'confirmed'),
        ));

        $this->app->singleton(HoldingsVerifier::class, function ($app): HoldingsVerifier {
            return match ($this->solanaProvider()) {
                'helius' => new HeliusHoldingsVerifier(
                    apiKey: (string) config('solana.helius.api_key'),
                    rpcUrl: (string) config('solana.helius.rpc_url'),
                    tzlaMint: config('solana.tzla_mint'),
                    nftCollectionMint: config('solana.nft_collection_mint'),
                    goldenTicketClassicCollection: config('solana.golden_ticket_classic_collection'),
                    goldenTicketCnftCollection: config('solana.golden_ticket_cnft_collection'),
                    cacheTtl: (int) config('solana.holdings_cache_ttl', 300),
                    stakeReader: $app->make(StakePositionReader::class),
                ),
                default => new StubHoldingsVerifier,
            };
        });

        $this->app->singleton(FeeVerifier::class, function ($app): FeeVerifier {
            return match ($this->solanaProvider()) {
                'helius' => new HeliusFeeVerifier(
                    apiKey:            (string) config('solana.helius.api_key'),
                    rpcUrl:            (string) config('solana.helius.rpc_url'),
                    treasuryAddress:   (string) config('game.submission_fees.treasury_address'),
                    toleranceLamports: (int)    config('game.submission_fees.tolerance_lamports', 1000),
                    maxAgeSeconds:     (int)    config('game.submission_fees.max_age_seconds', 3600),
                    commitment:        (string) config('solana.commitment', 'confirmed'),
                ),
                default => new StubFeeVerifier,
            };
        });

        $this->app->singleton(BlockhashService::class, fn ($app): BlockhashService => new BlockhashService(
            apiKey: (string) config('solana.helius.api_key'),
            rpcUrl: (string) config('solana.helius.rpc_url'),
            commitment: (string) config('solana.commitment', 'confirmed'),
        ));

        $this->app->singleton(SpamGuard::class, fn ($app): SpamGuard => new SpamGuard(
            maxPerWindow: (int) config('game.spam.max_guesses_per_minute', 10),
        ));

        $this->app->singleton(NonceService::class, fn ($app): NonceService => new NonceService(
            ttlSeconds: (int) config('solana.nonce_ttl', 300),
            domain: parse_url((string) config('solana.auth_domain', 'http://localhost'), PHP_URL_HOST) ?: 'localhost',
        ));

        $this->app->singleton(TelegramNotifier::class, fn ($app): TelegramNotifier => new TelegramNotifier(
            enabled: (bool) config('telegram.enabled', false) && ! $app->environment('testing'),
            botToken: config('telegram.bot_token'),
            chatId: config('telegram.chat_id') !== null ? (string) config('telegram.chat_id') : null,
            apiBase: (string) config('telegram.api_base', 'https://api.telegram.org'),
            timeout: (int) config('telegram.timeout', 5),
            messageThreadId: ($thread = config('telegram.message_thread_id')) !== null && $thread !== ''
                ? (int) $thread
                : null,
        ));
    }

    public function boot(): void
    {
        //
    }

    /**
     * Resolve the configured Solana provider, refusing to fall back to the
     * non-verifying stubs outside of local development.
     *
     * The stubs accept any fee signature and report fake holdings, so silently
     * defaulting to them in production would make every guess free.
     */
    private function solanaProvider(): string
    {
        $provider = strtolower(trim((string) config('solana.provider', 'stub')));

        if ($provider === 'helius') {
            return 'helius';
        }

        if ($provider !== 'stub') {
            throw new RuntimeException(
                "Unknown SOLANA_PROVIDER [{$provider}]. Expected 'helius' or 'stub'."
            );
        }

        if (! $this->app->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'SOLANA_PROVIDER=stub is not permitted in the '.$this->app->environment().' environment. '.
                'The stub verifiers accept any fee payment. Set SOLANA_PROVIDER=helius.'
            );
        }

        return 'stub';
    }
}
