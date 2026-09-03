<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Wallet;
use App\Models\Week;
use Illuminate\Console\Command;

final class WalletListCommand extends Command
{
    protected $signature = 'treasure:wallet:list
        {--week= : Filter to wallets that touched a specific week}
        {--completed : With --week, only show wallets that fully completed it}';

    protected $description = 'List wallets that have connected, optionally filtered by week activity.';

    public function handle(): int
    {
        $query = Wallet::query()->orderBy('last_seen_at', 'desc');

        if ($this->option('week') !== null) {
            $week = Week::query()->where('number', (int) $this->option('week'))->first();
            if ($week === null) {
                $this->error('Week not found.');

                return self::FAILURE;
            }

            if ($this->option('completed')) {
                $query->whereHas('weekCompletions', fn ($q) => $q->where('week_id', $week->id));
            } else {
                $query->whereHas('guesses.word', fn ($q) => $q->where('week_id', $week->id));
            }
        }

        $wallets = $query->limit(500)->get();

        if ($wallets->isEmpty()) {
            $this->info('No wallets match.');

            return self::SUCCESS;
        }

        $this->table(
            ['address', 'tzla', 'nfts', 'last_seen'],
            $wallets->map(fn (Wallet $w): array => [
                $w->address,
                $w->tzla_balance_cached ?? '—',
                $w->nft_count_cached ?? '—',
                $w->last_seen_at?->diffForHumans() ?? '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
