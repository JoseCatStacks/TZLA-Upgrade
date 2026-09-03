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

final class VerifyAndRecordUnstake implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 5 times with stepped backoff (seconds). */
    public int $tries = 5;
    public array $backoff = [3, 10, 30, 60, 120];

    public function __construct(
        private readonly string $wallet,
        private readonly string $unstakeTx,
    ) {}

    public function handle(HeliusService $helius, StakeVerificationService $verifier): void
    {
        $tx = $helius->getTransaction($this->unstakeTx);

        if ($tx === null) {
            throw new RuntimeException("Transaction {$this->unstakeTx} not yet confirmed.");
        }

        if (! $verifier->verifyUnstake($tx, $this->wallet)) {
            $this->fail(new RuntimeException(
                "Unstake tx {$this->unstakeTx} failed verification for wallet {$this->wallet}."
            ));
            return;
        }

        $unstakedAt = isset($tx['blockTime'])
            ? Carbon::createFromTimestamp($tx['blockTime'])
            : now();

        // A retried job must not clobber a newer interaction that has already
        // been recorded (the on-chain read below reflects CURRENT state, and
        // closing records with a timestamp older than their staked_at would
        // zero them out).
        if (StakeRecord::where('wallet', $this->wallet)->where('staked_at', '>=', $unstakedAt)->exists()) {
            return;
        }

        // The program supports PARTIAL unstakes (stake_amount is decremented,
        // not zeroed), and every unstake pays pending rewards and restarts
        // accrual. Mirror that: close the open position, and when the on-chain
        // account still holds a remainder, reopen it as a fresh position so the
        // card keeps tracking on-chain state. The remainder is read from the
        // wallet's own PDA-verified user_stake account, never from the request.
        $remainder = $this->onChainRemainder($helius, $verifier, $tx);

        StakeRecord::where('wallet', $this->wallet)
            ->whereNull('unstaked_at')
            ->update([
                'unstaked_at' => $unstakedAt,
                'unstake_tx'  => $this->unstakeTx,
            ]);

        if ($remainder !== null && $remainder['amount_raw'] !== '0') {
            // stake_tx is unique, so a retried job cannot double-insert.
            StakeRecord::firstOrCreate(
                ['stake_tx' => $this->unstakeTx],
                [
                    'wallet'     => $this->wallet,
                    'amount_raw' => $remainder['amount_raw'],
                    'nft_tier'   => $remainder['nft_tier'],
                    'staked_at'  => $unstakedAt,
                ],
            );
        }

        Cache::forget("staking:record:{$this->wallet}");
        Cache::store(config('oracle.store', 'redis'))->forget("staking:rewards:{$this->wallet}");
    }

    /**
     * The position remaining after the unstake, read from the PDA-verified
     * user_stake account. Throws (so the job retries) when the account cannot
     * be read — closing a partially-unstaked position outright would silently
     * drop the remainder from tracking. Returns null only when the verified
     * transaction exposes no user_stake account at all.
     *
     * @return array{amount_raw: string, nft_tier: int, bump: int}|null
     */
    private function onChainRemainder(HeliusService $helius, StakeVerificationService $verifier, array $tx): ?array
    {
        $account = $verifier->userStakeAccountFromUnstake($tx, $this->wallet);
        if ($account === null) {
            return null;
        }

        $position = $verifier->parseUserStakeAccount($helius->getAccountData($account));
        if ($position === null) {
            throw new RuntimeException(
                "user_stake account {$account} unreadable while recording unstake {$this->unstakeTx}."
            );
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
        logger()->warning('VerifyAndRecordUnstake exhausted retries', [
            'wallet'     => $this->wallet,
            'unstake_tx' => $this->unstakeTx,
            'error'      => $e->getMessage(),
        ]);
    }
}
