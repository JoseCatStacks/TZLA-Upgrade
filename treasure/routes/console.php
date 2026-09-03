<?php

use App\Models\FeePayment;
use App\Services\Wallet\NonceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('treasure:nonces:purge', function (NonceService $nonces) {
    $this->info(sprintf('Purged %d expired nonce(s).', $nonces->purgeExpired()));
})->purpose('Delete expired wallet auth nonces');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Requires a cron entry on the server:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

// Auth nonces are single-use; expired rows grow unbounded without this.
Schedule::command('treasure:nonces:purge')->hourly();

// Keeps eligibility current for wallets that have not logged in recently.
Schedule::command('treasure:holdings:refresh')->hourly()->withoutOverlapping();

// Spent fee signatures only need to be retained long enough to block replays.
// Keeping a year gives ample audit history without unbounded growth.
Schedule::call(function (): void {
    FeePayment::query()->where('created_at', '<', now()->subYear())->delete();
})->weekly()->name('prune-fee-payments')->withoutOverlapping();
