<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StakeRecord;
use App\Services\HeliusService;
use App\Services\StakeVerificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class VerifyAndRecordStake implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 5 times with stepped backoff (seconds). */
    public int $tries = 5;
    public array $backoff = [3, 10, 30, 60, 120];

    public function __construct(
        private readonly string $wallet,
        private readonly string $amountRaw,
        private readonly int    $nftTier,
        private readonly string $stakeTx,
    ) {}

    public function handle(HeliusService $helius, StakeVerificationService $verifier): void
    {
        $tx = $helius->getTransaction($this->stakeTx);

        if ($tx === null) {
            // Transaction not yet propagated — retry with backoff
            throw new RuntimeException("Transaction {$this->stakeTx} not yet confirmed.");
        }

        if (! $verifier->verifyStake($tx, $this->wallet, $this->amountRaw)) {
            // Verification failed permanently — do not retry
            $this->fail(new RuntimeException(
                "Stake tx {$this->stakeTx} failed verification for wallet {$this->wallet}."
            ));
            return;
        }

        $stakedAt = isset($tx['blockTime'])
            ? Carbon::createFromTimestamp($tx['blockTime'])
            : now();

        // A retried job must not clobber a newer interaction that has already
        // been recorded — the account read below reflects CURRENT chain state,
        // which belongs to the newest interaction, not this one.
        if (StakeRecord::where('wallet', $this->wallet)->where('staked_at', '>', $stakedAt)->exists()) {
            return;
        }

        // The amount and tier drive the displayed position and reward rate, so
        // both are read from the on-chain user_stake account rather than trusted
        // from the request. Crucially the program ADDS each stake to the account
        // (a "restake" tops up the position), so the recorded amount must be the
        // on-chain cumulative total — not this transaction's amount alone.
        $position = $this->onChainPosition($helius, $verifier, $tx);
        $nftTier  = $position['nft_tier'] ?? $this->nftTier;

        // Sum of the open positions being replaced, used both to close them and
        // as the cumulative fallback when the chain read is unavailable.
        $openAmountRaw = (string) StakeRecord::where('wallet', $this->wallet)
            ->whereNull('unstaked_at')
            ->get()
            ->reduce(fn (string $sum, StakeRecord $r): string => bcadd($sum, $r->amount_raw), '0');

        $amountRaw = $position !== null && $position['amount_raw'] !== '0'
            ? $position['amount_raw']
            : bcadd($openAmountRaw, $this->amountRaw);

        // Close any open position for this wallet before recording the new one.
        // The closed slice still counts toward trailing-window rewards, so a
        // claim/restake keeps pre-claim earnings on the card.
        StakeRecord::where('wallet', $this->wallet)
            ->whereNull('unstaked_at')
            ->update(['unstaked_at' => $stakedAt]);

        StakeRecord::create([
            'wallet'     => $this->wallet,
            'amount_raw' => $amountRaw,
            'nft_tier'   => $nftTier,
            'staked_at'  => $stakedAt,
            'stake_tx'   => $this->stakeTx,
        ]);

        Cache::forget("staking:record:{$this->wallet}");
        Cache::store(config('oracle.store', 'redis'))->forget("staking:rewards:{$this->wallet}");
    }

    /**
     * The position as the program recorded it — cumulative stake_amount and
     * nft_tier — read from the user_stake account the verified transaction
     * touched. The account address comes from the on-chain transaction and is
     * additionally proven to be the wallet's own PDA via the bump stored in the
     * account, so neither can be spoofed by the request. Returns null (caller
     * falls back to claimed values) only when the RPC read is unavailable —
     * never on a verification mismatch, which throws so the job retries and
     * surfaces.
     *
     * @return array{amount_raw: string, nft_tier: int, bump: int}|null
     */
    private function onChainPosition(HeliusService $helius, StakeVerificationService $verifier, array $tx): ?array
    {
        $account = $verifier->userStakeAccountFromStake($tx, $this->wallet);
        if ($account === null) {
            return null;
        }

        $position = $verifier->parseUserStakeAccount($helius->getAccountData($account));
        if ($position === null) {
            logger()->warning('VerifyAndRecordStake: user_stake account unreadable, using claimed values', [
                'wallet' => $this->wallet, 'account' => $account,
            ]);

            return null;
        }

        if (! $verifier->isUserStakePdaFor($account, $this->wallet, $position['bump'])) {
            throw new RuntimeException(
                "user_stake account {$account} is not the PDA of wallet {$this->wallet}."
            );
        }

        return $position;
    }

    public function failed(\Throwable $e): void
    {
        logger()->warning('VerifyAndRecordStake exhausted retries', [
            'wallet'   => $this->wallet,
            'stake_tx' => $this->stakeTx,
            'error'    => $e->getMessage(),
        ]);
    }
}
