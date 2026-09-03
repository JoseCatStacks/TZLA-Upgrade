<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use Carbon\Carbon;
use Illuminate\Console\Command;

final class WeekCreateCommand extends Command
{
    protected $signature = 'treasure:week:create
        {number : Week number (unique, e.g. 1)}
        {--title= : Optional display title}
        {--starts-at= : ISO8601 timestamp; defaults to now}
        {--reward= : Reward description}';

    protected $description = 'Create a new treasure hunt week.';

    public function handle(): int
    {
        $number = (int) $this->argument('number');
        if ($number <= 0) {
            $this->error('Week number must be a positive integer.');

            return self::INVALID;
        }

        if (Week::query()->where('number', $number)->exists()) {
            $this->error("Week {$number} already exists. Use treasure:week:update instead.");

            return self::FAILURE;
        }

        $startsAtRaw = $this->option('starts-at') ?? $this->ask('Starts at (ISO8601 or "now")', 'now');
        $startsAt = $startsAtRaw === 'now' ? now() : Carbon::parse((string) $startsAtRaw);

        $title = $this->option('title') ?? $this->ask('Title (optional)', '') ?: null;
        $reward = $this->option('reward') ?? $this->ask('Reward description', '') ?: null;

        $week = Week::create([
            'number' => $number,
            'title' => $title,
            'starts_at' => $startsAt,
            'reward_description' => $reward,
        ]);

        $this->info("Created week {$week->number}.");
        $this->table(['#', 'title', 'starts_at', 'reward'], [
            [$week->number, $week->title, $week->starts_at->toIso8601String(), $week->reward_description],
        ]);

        return self::SUCCESS;
    }
}
