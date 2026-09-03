<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Solana\StakePositionReader;
use Tests\TestCase;

final class StakePositionReaderTest extends TestCase
{
    public function test_user_stake_pda_matches_web3js(): void
    {
        $reader = new StakePositionReader(
            apiKey: 'test',
            rpcUrl: 'https://example.invalid',
            programId: '3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ',
            poolAddress: '2yYgVz8CDzvMFYZ2cfMy854RETrafVYSAAaUUJw9bAVV',
        );

        // Golden vector from @solana/web3.js findProgramAddressSync for this wallet.
        $this->assertSame(
            'GhUhwa7L6shDgHtbpKbnSfxNbQwfW7pYUKQDvWTJgWgr',
            $reader->findUserStakePda('DQLBoeyCkUuGMHmsEBBJ5LMzdXDza89NLEdzkbtjMfXq'),
        );
    }
}
