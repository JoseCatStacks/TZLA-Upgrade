<?php

declare(strict_types=1);

namespace App\Services\Solana;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the on-chain user_stake PDA for a wallet (same seeds as the staking app).
 */
final class StakePositionReader
{
    private const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** Ed25519 field prime: 2^255 - 19 */
    private const P = '57896044618658097711785492504343953926634992332820282019728792003956564819949';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $rpcUrl,
        private readonly string $programId,
        private readonly string $poolAddress,
        private readonly string $commitment = 'confirmed',
    ) {}

    /** TZLA currently locked in the stake account, or 0 when none / unreadable. */
    public function stakedAmount(string $walletAddress): float
    {
        if ($this->programId === '' || $this->poolAddress === '' || $this->apiKey === '') {
            return 0.0;
        }

        try {
            $pda = $this->findUserStakePda($walletAddress);
        } catch (\Throwable $e) {
            Log::warning('StakePositionReader: PDA derive failed', ['error' => $e->getMessage()]);

            return 0.0;
        }

        $data = $this->fetchAccountData($pda);
        if ($data === null || strlen($data) < 74) {
            return 0.0;
        }

        // Anchor account: 8-byte discriminator + pool pubkey(32) + stake_amount u64 @ offset 40.
        ['lo' => $lo, 'hi' => $hi] = unpack('Vlo/Vhi', substr($data, 40, 8));
        $raw = bcadd(bcmul((string) $hi, '4294967296'), (string) $lo);

        // TZLA uses 9 decimals on-chain.
        return (float) bcdiv($raw, '1000000000', 9);
    }

    public function findUserStakePda(string $walletAddress): string
    {
        $program = $this->base58Decode($this->programId);
        $pool = $this->base58Decode($this->poolAddress);
        $wallet = $this->base58Decode($walletAddress);

        for ($bump = 255; $bump >= 0; $bump--) {
            $hash = hash(
                'sha256',
                'user_stake'.$pool.$wallet.chr($bump).$program.'ProgramDerivedAddress',
                true,
            );
            if (! $this->isOnCurve($hash)) {
                return $this->base58Encode($hash);
            }
        }

        throw new \RuntimeException('Unable to find user_stake PDA bump.');
    }

    private function fetchAccountData(string $address): ?string
    {
        $url = rtrim($this->rpcUrl, '/').'/?api-key='.$this->apiKey;

        try {
            $response = Http::timeout(10)->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 'tzla-stake',
                'method'  => 'getAccountInfo',
                'params'  => [$address, ['encoding' => 'base64', 'commitment' => $this->commitment]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('StakePositionReader RPC failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $b64 = data_get($response->json(), 'result.value.data.0');
        if (! is_string($b64) || $b64 === '') {
            return null;
        }

        $raw = base64_decode($b64, true);

        return $raw === false ? null : $raw;
    }

    /**
     * True when $pubkey is a valid compressed EdwardsY point on ed25519.
     *
     * Must match Solana's curve25519-dalek decompress check — NOT libsodium's
     * pk_to_curve25519, which also requires the prime-order subgroup and
     * misclassifies some on-curve bumps (so we pick the wrong PDA).
     */
    private function isOnCurve(string $pubkey): bool
    {
        if (strlen($pubkey) !== 32) {
            return false;
        }

        $bytes = array_values(unpack('C*', $pubkey));
        $bytes[31] &= 0x7f; // clear x-sign bit → recover y

        $y = '0';
        for ($i = 0; $i < 32; $i++) {
            $y = bcadd($y, bcmul((string) $bytes[$i], bcpow('2', (string) (8 * $i), 0), 0), 0);
        }

        $p = self::P;
        if (bccomp($y, $p) >= 0) {
            return false;
        }

        // d = -121665/121666 mod p  (ed25519 twisted Edwards constant)
        $d = bcmod(bcmul('-121665', $this->modInverse('121666', $p), 0), $p);
        if (bccomp($d, '0') < 0) {
            $d = bcadd($d, $p, 0);
        }

        $y2 = bcmod(bcmul($y, $y, 0), $p);
        $u = bcmod(bcsub($y2, '1', 0), $p);                 // y² - 1
        $v = bcmod(bcadd(bcmul($d, $y2, 0), '1', 0), $p);   // d·y² + 1
        if (bccomp($u, '0') < 0) {
            $u = bcadd($u, $p, 0);
        }
        if (bccomp($v, '0') < 0) {
            $v = bcadd($v, $p, 0);
        }

        // x² = u · v⁻¹  must be a quadratic residue mod p
        $x2 = bcmod(bcmul($u, $this->modInverse($v, $p), 0), $p);

        return $this->isQuadraticResidue($x2, $p);
    }

    private function modInverse(string $a, string $p): string
    {
        // a^(p-2) ≡ a⁻¹ (mod p) for prime p
        return bcpowmod($a, bcsub($p, '2', 0), $p);
    }

    private function isQuadraticResidue(string $a, string $p): bool
    {
        if (bccomp($a, '0') === 0) {
            return true;
        }

        // Euler criterion: a^((p-1)/2) ≡ 1 (mod p) iff square
        $legendre = bcpowmod($a, bcdiv(bcsub($p, '1', 0), '2', 0), $p);

        return bccomp($legendre, '1') === 0;
    }

    private function base58Decode(string $encoded): string
    {
        $num = '0';
        foreach (str_split($encoded) as $char) {
            $index = strpos(self::BASE58, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Invalid base58 character.');
            }
            $num = bcadd(bcmul($num, '58'), (string) $index);
        }

        $bytes = '';
        while (bccomp($num, '0') > 0) {
            $bytes = chr((int) bcmod($num, '256')).$bytes;
            $num = bcdiv($num, '256', 0);
        }

        $leading = 0;
        foreach (str_split($encoded) as $char) {
            if ($char === '1') {
                $leading++;
            } else {
                break;
            }
        }

        return str_pad(str_repeat("\x00", $leading).$bytes, 32, "\x00", STR_PAD_LEFT);
    }

    private function base58Encode(string $bytes): string
    {
        $zeros = 0;
        $len = strlen($bytes);
        while ($zeros < $len && $bytes[$zeros] === "\x00") {
            $zeros++;
        }

        $num = '0';
        for ($i = 0; $i < $len; $i++) {
            $num = bcadd(bcmul($num, '256'), (string) ord($bytes[$i]));
        }

        $out = '';
        while (bccomp($num, '0') > 0) {
            $out = self::BASE58[(int) bcmod($num, '58')].$out;
            $num = bcdiv($num, '58', 0);
        }

        return str_repeat('1', $zeros).$out;
    }
}
