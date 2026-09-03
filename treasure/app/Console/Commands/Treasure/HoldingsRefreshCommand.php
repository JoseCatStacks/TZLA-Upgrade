<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Jobs\RefreshWalletHoldings;
use App\Models\Wallet;
use Illuminate\Console\Command;

final class HoldingsRefreshCommand extends Command
{
    protected $signature = 'treasure:holdings:refresh
        {address? : Specific wallet address, omit for all}
        {--sync : Run synchronously instead of dispatching to the queue}';

    protected $description = 'Refresh cached TZLA balance and NFT count for one or all wallets.';

    public function handle(): int
    {
        $wallets = $this->argument('address') !== null
            ? Wallet::query()->where('address', $this->argument('address'))->get()
            : Wallet::query()->get();

        if ($wallets->isEmpty()) {
            $this->info('No wallets to refresh.');

            return self::SUCCESS;
        }

        foreach ($wallets as $wallet) {
            if ($this->option('sync')) {
                RefreshWalletHoldings::dispatchSync($wallet);
                $this->line("Refreshed {$wallet->address}");
            } else {
                RefreshWalletHoldings::dispatch($wallet);
                $this->line("Queued refresh for {$wallet->address}");
            }
        }

        $this->info(sprintf('%d wallet(s) processed.', $wallets->count()));

        return self::SUCCESS;
    }
}
