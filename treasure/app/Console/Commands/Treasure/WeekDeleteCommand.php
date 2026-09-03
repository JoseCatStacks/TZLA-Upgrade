<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use Illuminate\Console\Command;

final class WeekDeleteCommand extends Command
{
    protected $signature = 'treasure:week:delete
        {number : Week number to delete}
        {--force : Skip confirmation}';

    protected $description = 'Delete a week and all its words, guesses, and completions.';

    public function handle(): int
    {
        $week = Week::query()->where('number', (int) $this->argument('number'))->first();
        if ($week === null) {
            $this->error('Week not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Delete week {$week->number} and cascade to all guesses/completions?", false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $week->delete();
        $this->info("Deleted week {$week->number}.");

        return self::SUCCESS;
    }
}
