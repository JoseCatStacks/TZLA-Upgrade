<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use App\Models\Word;
use App\Services\Guess\GuessNormalizer;
use Illuminate\Console\Command;

final class WordSetCommand extends Command
{
    protected $signature = 'treasure:word:set
        {week : Week number}
        {position : Word position (1-based, e.g. 1, 2, 3)}
        {--answer= : The correct word (stored normalized)}
        {--hint= : Hint text shown to players}';

    protected $description = 'Create or update a word for a week (upsert).';

    public function handle(GuessNormalizer $normalizer): int
    {
        $week = Week::query()->where('number', (int) $this->argument('week'))->first();
        if ($week === null) {
            $this->error('Week not found. Create it first with treasure:week:create.');

            return self::FAILURE;
        }

        $position = (int) $this->argument('position');
        if ($position < 1) {
            $this->error('Position must be a positive integer.');

            return self::INVALID;
        }

        $answer = $this->option('answer') ?? $this->ask('Answer');
        $hint = $this->option('hint') ?? $this->ask('Hint (optional)', '') ?: null;

        $normalized = $normalizer->normalize((string) $answer);
        if ($normalized === '') {
            $this->error('Answer normalizes to empty string. Use alphanumerics.');

            return self::INVALID;
        }

        $word = Word::updateOrCreate(
            ['week_id' => $week->id, 'position' => $position],
            ['answer_normalized' => $normalized, 'hint' => $hint],
        );

        $this->info("Set word {$position} for week {$week->number}: {$normalized}");
        $this->table(['pos', 'answer_normalized', 'hint'], [
            [$word->position, $word->answer_normalized, $word->hint],
        ]);

        return self::SUCCESS;
    }
}
