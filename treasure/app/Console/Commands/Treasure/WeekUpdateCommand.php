<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class WeekUpdateCommand extends Command
{
    protected $signature = 'treasure:week:update
        {number : Week number to update}
        {--title=}
        {--starts-at=}
        {--reward=}';

    protected $description = 'Update an existing week.';

    public function handle(): int
    {
        $week = Week::query()->where('number', (int) $this->argument('number'))->first();
        if ($week === null) {
            $this->error('Week not found.');

            return self::FAILURE;
        }

        if ($this->option('title') !== null) {
            $week->title = (string) $this->option('title');
        }
        if ($this->option('starts-at') !== null) {
            $week->starts_at = Carbon::parse((string) $this->option('starts-at'));
        }
        if ($this->option('reward') !== null) {
            $week->reward_description = (string) $this->option('reward');
        }

        $week->save();

        $this->info("Updated week {$week->number}.");
        $this->table(['#', 'title', 'starts_at', 'reward'], [
            [$week->number, $week->title, $week->starts_at->toIso8601String(), $week->reward_description],
        ]);

        return self::SUCCESS;
    }
}
