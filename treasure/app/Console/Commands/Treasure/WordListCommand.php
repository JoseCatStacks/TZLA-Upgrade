<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use App\Models\Word;
use Illuminate\Console\Command;

final class WordListCommand extends Command
{
    protected $signature = 'treasure:word:list {week : Week number}';

    protected $description = 'List all words for a week.';

    public function handle(): int
    {
        $week = Week::query()->where('number', (int) $this->argument('week'))->first();
        if ($week === null) {
            $this->error('Week not found.');

            return self::FAILURE;
        }

        $words = $week->words()->withCount('completions')->get();
        if ($words->isEmpty()) {
            $this->info('No words set. Use treasure:word:set.');

            return self::SUCCESS;
        }

        $this->table(
            ['pos', 'answer', 'hint', 'completions'],
            $words->map(fn (Word $w): array => [
                $w->position, $w->answer_normalized, $w->hint ?? '—', $w->completions_count,
            ])->all()
        );

        return self::SUCCESS;
    }
}
