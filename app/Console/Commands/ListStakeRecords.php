<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StakeRecord;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

final class ListStakeRecords extends Command
{
    protected $signature = 'stake:records
        {--days= : Filter to records staked within the last N days}
        {--status= : Filter by status: active, unstaked, or all (default: all)}
        {--wallet= : Filter by a specific wallet address}';

    protected $description = 'Print all staking transaction records from the database';

    private const NFT_TIER = [0 => 'Bronze', 1 => 'Silver', 2 => 'Gold'];

    public function handle(): int
    {
        $query = StakeRecord::query()->orderBy('staked_at', 'desc');

        $this->applyDaysFilter($query);
        $this->applyStatusFilter($query);
        $this->applyWalletFilter($query);

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->warn('No stake records found matching the given filters.');
            return self::SUCCESS;
        }

        $this->printSummary($records->count());

        $rows = $records->map(fn (StakeRecord $r): array => [
            $r->id,
            $this->shortWallet($r->wallet),
            self::NFT_TIER[$r->nft_tier] ?? "Tier {$r->nft_tier}",
            $this->formatTokens($r->amount_raw),
            $r->staked_at->format('Y-m-d H:i'),
            $r->unstaked_at ? $r->unstaked_at->format('Y-m-d H:i') : '—',
            $r->unstaked_at ? '—' : $this->formatTokens($r->predictedYieldRaw()),
            $this->shortTx($r->stake_tx),
            $this->shortTx($r->unstake_tx),
        ])->toArray();

        $this->table(
            ['ID', 'Wallet', 'Tier', 'Staked (tokens)', 'Staked At', 'Unstaked At', 'Accrued Yield', 'Stake TX', 'Unstake TX'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function applyDaysFilter(Builder $query): void
    {
        $days = $this->option('days');
        if ($days === null) {
            return;
        }

        if (! ctype_digit((string) $days) || (int) $days <= 0) {
            $this->error('--days must be a positive integer.');
            exit(self::FAILURE);
        }

        $query->where('staked_at', '>=', now()->subDays((int) $days));
        $this->line("<fg=cyan>Filtering to records staked within the last {$days} day(s).</>");
    }

    private function applyStatusFilter(Builder $query): void
    {
        $status = $this->option('status');
        match ($status) {
            'active'   => $query->whereNull('unstaked_at'),
            'unstaked' => $query->whereNotNull('unstaked_at'),
            null, 'all' => null,
            default    => $this->warn("Unknown --status value '{$status}', showing all."),
        };

        if ($status && $status !== 'all') {
            $this->line("<fg=cyan>Filtering to {$status} stakes.</>");
        }
    }

    private function applyWalletFilter(Builder $query): void
    {
        $wallet = $this->option('wallet');
        if ($wallet === null) {
            return;
        }

        $query->where('wallet', $wallet);
        $this->line("<fg=cyan>Filtering to wallet: {$wallet}</>");
    }

    private function printSummary(int $count): void
    {
        $this->info("Found {$count} stake record(s).");
    }

    private function shortWallet(string $wallet): string
    {
        return strlen($wallet) > 12
            ? substr($wallet, 0, 6) . '…' . substr($wallet, -6)
            : $wallet;
    }

    private function shortTx(?string $tx): string
    {
        if ($tx === null) return '—';
        return strlen($tx) > 14 ? substr($tx, 0, 8) . '…' : $tx;
    }

    private function formatTokens(string $raw): string
    {
        // 10^9 decimals — display with up to 4 decimal places
        $tokens = bcdiv($raw, '1000000000', 4);
        return number_format((float) $tokens, 4);
    }
}
