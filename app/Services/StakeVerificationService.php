<?php

declare(strict_types=1);

namespace App\Services;

final class StakeVerificationService
{
    // Anchor discriminators: sha256("global:<instruction_name>")[0..7].
    // All stake/unstake variants share the same account order and put
    // stake_amount (u64 LE) first in the instruction args, so one code path
    // verifies the plain, golden-cNFT (with_ticket) and golden-classic
    // (with_nft_ticket) instructions alike.
    private const STAKE_DISCS = [
        "\xce\xb0\xca\x12\xc8\xd1\xb3\x6c", // stake
        "\x28\x7d\x23\x20\x35\x32\x40\xf3", // stake_with_ticket
        "\x71\x95\xf9\xba\xde\xdc\x3f\x69", // stake_with_nft_ticket
    ];

    private const UNSTAKE_DISCS = [
        "\x5a\x5f\x6b\x2a\xcd\x7c\x32\xe1", // unstake
        "\x1e\xd5\x1a\x5f\x98\xd1\x3d\x06", // unstake_with_ticket
        "\x9b\x72\xa7\x60\xcc\xad\x3b\xf2", // unstake_with_nft_ticket
    ];

    // user_stake is the 6th account of every stake/unstake instruction
    // (user, pool, user_token_account, stake_token_vault, reward_token_vault,
    // user_stake, …) — see the IDL mirrored in resources/js/staking.ts.
    private const USER_STAKE_ACCOUNT_INDEX = 5;

    public function verifyStake(array $tx, string $wallet, string $expectedAmountRaw): bool
    {
        return $this->matchInstruction($tx, $wallet, self::STAKE_DISCS, $expectedAmountRaw) !== null;
    }

    public function verifyUnstake(array $tx, string $wallet): bool
    {
        return $this->matchInstruction($tx, $wallet, self::UNSTAKE_DISCS, null) !== null;
    }

    /**
     * Address of the user_stake PDA touched by the verified stake instruction,
     * or null when the transaction does not verify. Taken from the on-chain
     * transaction itself, so the caller can trust it as a chain-state pointer.
     */
    public function userStakeAccountFromStake(array $tx, string $wallet): ?string
    {
        return $this->userStakeAccount($tx, $wallet, self::STAKE_DISCS);
    }

    /** As userStakeAccountFromStake, for the unstake instruction variants. */
    public function userStakeAccountFromUnstake(array $tx, string $wallet): ?string
    {
        return $this->userStakeAccount($tx, $wallet, self::UNSTAKE_DISCS);
    }

    private function userStakeAccount(array $tx, string $wallet, array $discriminators): ?string
    {
        $ix = $this->matchInstruction($tx, $wallet, $discriminators, null);
        if ($ix === null) {
            return null;
        }

        $accountKeys = $tx['transaction']['message']['accountKeys'] ?? [];
        $index       = $ix['accounts'][self::USER_STAKE_ACCOUNT_INDEX] ?? -1;

        return $accountKeys[$index] ?? null;
    }

    /**
     * Decode the fields of a raw UserStake account that mirror the position:
     * the cumulative staked amount (the program ADDS each stake to the account
     * and SUBTRACTS partial unstakes), the tier, and the PDA bump used to prove
     * ownership. Layout: 8 discriminator, 32 pool, 8 stake_amount (u64 LE),
     * 16 legacy_reward_debt, 8 last_stake_time, 1 nft_tier, 1 bump.
     *
     * @return array{amount_raw: string, nft_tier: int, bump: int}|null
     */
    public function parseUserStakeAccount(?string $data): ?array
    {
        if ($data === null || strlen($data) < 74) {
            return null;
        }

        ['lo' => $lo, 'hi' => $hi] = unpack('Vlo/Vhi', substr($data, 40, 8));

        return [
            'amount_raw' => bcadd(bcmul((string) $hi, '4294967296'), (string) $lo),
            'nft_tier'   => ord($data[72]),
            'bump'       => ord($data[73]),
        ];
    }

    /**
     * Whether $accountAddress is the user_stake PDA for $wallet, i.e.
     * sha256("user_stake" ‖ pool ‖ wallet ‖ bump ‖ program_id ‖
     * "ProgramDerivedAddress") reproduces the address. $bump comes from the
     * account's own data, so a match is possible for exactly one wallet.
     */
    public function isUserStakePdaFor(string $accountAddress, string $wallet, int $bump): bool
    {
        $hash = hash(
            'sha256',
            'user_stake'
                . $this->base58Decode((string) config('oracle.global.pool.address'))
                . $this->base58Decode($wallet)
                . chr($bump)
                . $this->base58Decode((string) config('staking.program_id'))
                . 'ProgramDerivedAddress',
            true,
        );

        return hash_equals($this->base58Decode($accountAddress), $hash);
    }

    /**
     * Find the single top-level instruction that targets our program, carries
     * one of the expected discriminators, and (when given) matches the claimed
     * amount — with the claimed wallet as a transaction signer. Returns the raw
     * instruction, or null when nothing verifies.
     */
    private function matchInstruction(array $tx, string $wallet, array $discriminators, ?string $expectedAmountRaw): ?array
    {
        // Transaction must have succeeded on-chain: meta must be present with an
        // explicit err of null. (`$tx['meta']['err'] ?? 'missing'` would misfire
        // here — null coalescing triggers exactly when err IS null, i.e. success.)
        $meta = $tx['meta'] ?? null;
        if (! is_array($meta) || ! array_key_exists('err', $meta) || $meta['err'] !== null) {
            return null;
        }

        $message     = $tx['transaction']['message'] ?? [];
        $accountKeys = $message['accountKeys'] ?? [];
        $numSigners  = $message['header']['numRequiredSignatures'] ?? 1;
        $programId   = (string) config('staking.program_id');

        // The claimed wallet must appear as a signer on the transaction
        $signers = array_slice($accountKeys, 0, (int) $numSigners);
        if (! in_array($wallet, $signers, true)) {
            return null;
        }

        // One top-level instruction must target our program with an expected discriminator
        foreach ($message['instructions'] ?? [] as $ix) {
            $ixProgramId = $accountKeys[$ix['programIdIndex'] ?? -1] ?? null;
            if ($ixProgramId !== $programId) {
                continue;
            }

            $data = $this->base58Decode((string) ($ix['data'] ?? ''));
            if (strlen($data) >= 8 && in_array(substr($data, 0, 8), $discriminators, true)) {
                if ($expectedAmountRaw !== null) {
                    // Anchor encodes the first instruction arg (stake_amount: u64) as
                    // a little-endian 8-byte integer immediately after the discriminator.
                    if (strlen($data) < 16) {
                        return null;
                    }
                    ['lo' => $lo, 'hi' => $hi] = unpack('Vlo/Vhi', substr($data, 8, 8));
                    $onChainAmount = gmp_strval(
                        gmp_add(gmp_init($lo), gmp_mul(gmp_init($hi), gmp_init('4294967296')))
                    );
                    if ($onChainAmount !== $expectedAmountRaw) {
                        return null;
                    }
                }
                return $ix;
            }
        }

        return null;
    }

    private function base58Decode(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $n = gmp_init(0);

        for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
            $pos = strpos($alphabet, $encoded[$i]);
            if ($pos === false) {
                return '';
            }
            $n = gmp_add(gmp_mul($n, 58), $pos);
        }

        $hex = gmp_strval($n, 16);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        $bytes = hex2bin($hex) ?: '';

        // Preserve leading zero bytes (Base58 encodes them as leading '1' characters)
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($encoded) && $encoded[$i] === '1'; $i++) {
            $leadingZeros++;
        }

        return str_repeat("\x00", $leadingZeros) . $bytes;
    }
}
