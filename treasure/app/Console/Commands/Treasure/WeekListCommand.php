<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use Illuminate\Console\Command;

final class WeekListCommand extends Command
{
    protected $signature = 'treasure:week:list';

    protected $description = 'List all weeks.';

    public function handle(): int
    {
        $weeks = Week::query()->withCount(['words', 'completions'])->orderBy('number')->get();

        if ($weeks->isEmpty()) {
            $this->info('No weeks defined yet. Use treasure:week:create.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'title', 'starts_at', 'unlocked', 'reward', 'words', 'completions'],
            $weeks->map(fn (Week $w): array => [
                $w->number,
                $w->title ?? '—',
                $w->starts_at?->toDateTimeString() ?? '—',
                $w->isUnlocked() ? 'yes' : 'no',
                $w->reward_description ?? '—',
                $w->words_count,
                $w->completions_count,
            ])->all()
        );

        return self::SUCCESS;
    }
}
