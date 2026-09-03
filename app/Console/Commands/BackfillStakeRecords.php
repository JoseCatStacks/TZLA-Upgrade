<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StakeRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * One-time backfill of stake_records from on-chain state.
 *
 * Wallets that staked before the record-stake tracking endpoint went live
 * (mid-May 2026) have no stake_records row, so their reward summaries — and
 * the shareable earnings card — read zero. This command reads every UserStake
 * account of the pool straight from the chain and inserts an open record for
 * each staked wallet that has none.
 *
 * The inserted staked_at is the on-chain last_stake_time, which is exactly
 * when on-chain reward accrual (re)started for the position, so backfilled
 * rows produce the same trailing-window rewards the program itself would pay.
 *
 * Idempotent: wallets with an existing open record are skipped, so it is safe
 * to re-run and safe to run while live traffic writes new records.
 */
class BackfillStakeRecords extends Command
{
    protected $signature = 'staking:backfill
        {--dry-run : Report what would be inserted without writing}';

    protected $description = 'Backfill stake_records from on-chain UserStake accounts (positions opened before tracking existed)';

    /** Byte offsets inside a UserStake account (see sol-stake lib.rs). */
    private const OFFSET_POOL       = 8;   // after the 8-byte Anchor discriminator
    private const OFFSET_AMOUNT     = 40;  // u64
    private const OFFSET_STAKE_TIME = 64;  // i64 (skips the u128 legacy_reward_debt)
    private const OFFSET_NFT_TIER   = 72;  // u8
    private const OFFSET_BUMP       = 73;  // u8 — PDA bump, used to verify ownership

    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private string $endpoint;
    private string $programIdBytes;
    private string $poolBytes;

    public function handle(): int
    {
        $apiKey = (string) config('services.helius.api_key', '');
        if ($apiKey === '') {
            $this->error('services.helius.api_key is not configured.');

            return self::FAILURE;
        }
        $this->endpoint = "https://mainnet.helius-rpc.com/?api-key={$apiKey}";

        $programId = (string) config('staking.program_id');
        $pool      = (string) config('oracle.global.pool.address');
        $dryRun    = (bool) $this->option('dry-run');

        $this->info("Program: {$programId}");
        $this->info("Pool:    {$pool}");

        $this->programIdBytes = $this->base58Decode($programId);
        $this->poolBytes      = $this->base58Decode($pool);

        // Every UserStake account embeds the pool pubkey right after the
        // discriminator, so one memcmp filter enumerates all stakers.
        $accounts = $this->rpc('getProgramAccounts', [
            $programId,
            [
                'encoding' => 'base64',
                'filters'  => [
                    ['memcmp' => ['offset' => self::OFFSET_POOL, 'bytes' => $pool]],
                ],
            ],
        ]);

        $this->info('UserStake accounts found: ' . count($accounts));

        $inserted = 0;
        $healed = 0;
        $skippedTracked = 0;
        $skippedEmpty = 0;
        $failed = 0;

        foreach ($accounts as $entry) {
            $accountPubkey = $entry['pubkey'];
            $data = base64_decode($entry['account']['data'][0], true);

            if ($data === false || strlen($data) < self::OFFSET_NFT_TIER + 1) {
                $this->warn("  {$accountPubkey}: unexpected account size, skipped");
                $failed++;
                continue;
            }

            $amountRaw     = $this->u64At($data, self::OFFSET_AMOUNT);
            $lastStakeTime = $this->i64At($data, self::OFFSET_STAKE_TIME);
            $nftTier       = ord($data[self::OFFSET_NFT_TIER]);
            $bump          = ord($data[self::OFFSET_BUMP]);

            if ($amountRaw === '0') {
                $skippedEmpty++;
                continue;
            }

            usleep(250_000); // pace the history calls per account under Helius rate limits

            [$wallet, $signature] = $this->resolveOwner($accountPubkey, $bump);
            if ($wallet === null) {
                $this->warn("  {$accountPubkey}: could not resolve owner wallet, skipped");
                $failed++;
                continue;
            }

            $stakedAt = Carbon::createFromTimestampUTC($lastStakeTime);
            $tokens   = bcdiv($amountRaw, (string) config('staking.token_base_units'), 2);

            // Reconcile against what is already tracked. The DB may drift from
            // the chain (a missed event, or a top-up recorded before cumulative
            // tracking existed): the open position must always mirror the
            // account's cumulative stake_amount and tier.
            $openRecords = StakeRecord::openForWallet($wallet)->get();
            $openSum     = (string) $openRecords->reduce(
                fn (string $sum, StakeRecord $r): string => bcadd($sum, $r->amount_raw), '0'
            );

            if ($openRecords->isNotEmpty()
                && bccomp($openSum, $amountRaw) === 0
                && (int) $openRecords->last()->nft_tier === $nftTier) {
                $skippedTracked++;
                continue;
            }

            $isHeal = $openRecords->isNotEmpty();
            $this->line(sprintf(
                '  %s %s  %s TZLA  tier %d  staked_at %s',
                $isHeal ? '~' : '+', $wallet, $tokens, $nftTier, $stakedAt->toDateTimeString(),
            ));

            if (! $dryRun) {
                // When the drift comes from the very transaction we resolved
                // (e.g. a top-up recorded with only its own amount), correct
                // that record in place; otherwise close the stale open
                // position at last_stake_time — exactly when on-chain accrual
                // restarted — and open a fresh one with the chain values.
                $existing = StakeRecord::where('stake_tx', $signature)->first();

                StakeRecord::openForWallet($wallet)
                    ->when($existing !== null, fn ($q) => $q->whereKeyNot($existing->getKey()))
                    ->update(['unstaked_at' => $stakedAt]);

                if ($existing !== null) {
                    $existing->update([
                        'amount_raw'  => $amountRaw,
                        'nft_tier'    => $nftTier,
                        'staked_at'   => $stakedAt,
                        'unstaked_at' => null,
                    ]);
                } else {
                    StakeRecord::create([
                        'wallet'     => $wallet,
                        'amount_raw' => $amountRaw,
                        'nft_tier'   => $nftTier,
                        'staked_at'  => $stakedAt,
                        'stake_tx'   => $signature,
                    ]);
                }
            }
            $isHeal ? $healed++ : $inserted++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d inserted, %d healed, %d already tracked, %d empty positions, %d failed.',
            $dryRun ? 'Dry run' : 'Done',
            $inserted, $healed, $skippedTracked, $skippedEmpty, $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve the owner wallet of a user_stake PDA. The user pubkey is a PDA
     * seed but is not stored in the account, so candidates are taken from the
     * signers of recent transactions touching the account — and each candidate
     * is verified cryptographically by re-deriving the PDA from
     * ["user_stake", pool, candidate, bump] and comparing it to the account
     * address. Anyone can craft a transaction that merely references the
     * account, so an unverified "latest signer" must never be trusted.
     *
     * @return array{0: ?string, 1: ?string} [wallet, signature]
     */
    private function resolveOwner(string $accountPubkey, int $bump): array
    {
        $signatures = $this->rpc('getSignaturesForAddress', [
            $accountPubkey,
            ['limit' => 20],
        ]);

        $accountBytes = $this->base58Decode($accountPubkey);

        foreach ($signatures as $entry) {
            $signature = $entry['signature'] ?? null;
            if ($signature === null) {
                continue;
            }

            $tx = $this->rpc('getTransaction', [
                $signature,
                ['encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0],
            ]);

            foreach ($tx['transaction']['message']['accountKeys'] ?? [] as $key) {
                if (($key['signer'] ?? false) !== true) {
                    continue;
                }
                if ($this->isUserStakePda($accountBytes, $key['pubkey'], $bump)) {
                    return [$key['pubkey'], $signature];
                }
            }

            usleep(250_000);
        }

        return [null, null];
    }

    /**
     * Whether $accountBytes is the user_stake PDA for $candidateWallet, i.e.
     * sha256("user_stake" ‖ pool ‖ candidate ‖ bump ‖ program_id ‖
     * "ProgramDerivedAddress") equals the account address. Uses the bump stored
     * in the account itself, so a match is possible for exactly one wallet.
     */
    private function isUserStakePda(string $accountBytes, string $candidateWallet, int $bump): bool
    {
        $hash = hash(
            'sha256',
            'user_stake'
                . $this->poolBytes
                . $this->base58Decode($candidateWallet)
                . chr($bump)
                . $this->programIdBytes
                . 'ProgramDerivedAddress',
            true,
        );

        return hash_equals($accountBytes, $hash);
    }

    /** Decode a base58 string (Solana pubkey) to raw bytes. */
    private function base58Decode(string $encoded): string
    {
        $num = '0';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::BASE58_ALPHABET, $char);
            if ($index === false) {
                throw new \InvalidArgumentException("Invalid base58 character in: {$encoded}");
            }
            $num = bcadd(bcmul($num, '58'), (string) $index);
        }

        $bytes = '';
        while (bccomp($num, '0') > 0) {
            $bytes = chr((int) bcmod($num, '256')) . $bytes;
            $num   = bcdiv($num, '256', 0);
        }

        // Leading '1' characters encode leading zero bytes.
        return str_repeat("\0", strlen($encoded) - strlen(ltrim($encoded, '1'))) . $bytes;
    }

    private function rpc(string $method, array $params): mixed
    {
        $attempts = 0;

        while (true) {
            $json = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpoint, [
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => $method,
                    'params'  => $params,
                ])
                ->json();

            if (is_array($json) && ! isset($json['error'])) {
                return $json['result'] ?? null;
            }

            // Back off and retry on rate limiting; a backfill has no deadline.
            $rateLimited = ($json['error']['code'] ?? null) === -32429;
            if ($rateLimited && ++$attempts <= 8) {
                sleep(min(2 ** $attempts, 30));
                continue;
            }

            throw new \RuntimeException(
                "Helius error for {$method}: " . json_encode($json['error'] ?? 'no response'),
            );
        }
    }

    /** Little-endian u64 at $offset, as a decimal string (safe beyond PHP_INT_MAX). */
    private function u64At(string $data, int $offset): string
    {
        ['lo' => $lo, 'hi' => $hi] = unpack('Vlo/Vhi', substr($data, $offset, 8));

        return bcadd(bcmul((string) $hi, '4294967296'), (string) $lo);
    }

    /** Little-endian i64 at $offset. Unix timestamps are positive and fit in PHP int. */
    private function i64At(string $data, int $offset): int
    {
        return unpack('P', substr($data, $offset, 8))[1];
    }
}
