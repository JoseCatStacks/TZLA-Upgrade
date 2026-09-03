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

        ['lo' => $lo, 'hi' => $hi] = unpack('Vlo/Vhi', substr($data, 40, 8));
        $raw = bcadd(bcmul((string) $hi, '4294967296'), (string) $lo);

        // TZLA uses 9 decimals on-chain.
        return (float) bcdiv($raw, '1000000000', 9);
    }

    private function findUserStakePda(string $walletAddress): string
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

    private function isOnCurve(string $pubkey): bool
    {
        try {
            sodium_crypto_sign_ed25519_pk_to_curve25519($pubkey);

            return true;
        } catch (\Throwable) {
            return false;
        }
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
