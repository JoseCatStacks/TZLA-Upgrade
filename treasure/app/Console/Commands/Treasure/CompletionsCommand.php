<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Models\Week;
use App\Models\WeekCompletion;
use App\Models\WordCompletion;
use App\Services\Guess\PrizeLadder;
use Illuminate\Console\Command;

final class CompletionsCommand extends Command
{
    protected $signature = 'treasure:completions
        {--week= : Filter to a specific week number}
        {--words : Show individual word completions instead of week completions}';

    protected $description = 'Audit view of completions for manual reward payout.';

    public function handle(PrizeLadder $prizes): int
    {
        if ($this->option('words')) {
            return $this->listWordCompletions();
        }

        return $this->listWeekCompletions($prizes);
    }

    private function listWeekCompletions(PrizeLadder $prizes): int
    {
        $query = WeekCompletion::query()
            ->with(['wallet', 'week'])
            ->orderBy('id');

        if ($this->option('week') !== null) {
            $week = Week::query()->where('number', (int) $this->option('week'))->first();
            if ($week === null) {
                $this->error('Week not found.');

                return self::FAILURE;
            }
            $query->where('week_id', $week->id);
        }

        $completions = $query->limit(500)->get();

        if ($completions->isEmpty()) {
            $this->info('No week completions yet.');

            return self::SUCCESS;
        }

        $placeById = [];
        $completions->groupBy('week_id')->each(function ($rows) use (&$placeById): void {
            $i = 1;
            foreach ($rows->sortBy('id') as $row) {
                $placeById[$row->id] = $i++;
            }
        });

        $this->table(
            ['week', 'place', 'prize_xmr', 'username', 'solana', 'xmr', 'completed_at'],
            $completions->map(function (WeekCompletion $c) use ($prizes, $placeById): array {
                $place = $placeById[$c->id] ?? 0;
                $prize = $prizes->prizeXmr($place);

                return [
                    $c->week?->number,
                    $place,
                    $prize === null ? 'unpaid' : $prize,
                    $c->wallet?->username ?: '—',
                    $c->wallet?->address,
                    $c->wallet?->payout_address ?: '—',
                    $c->completed_at?->toDateTimeString(),
                ];
            })->all()
        );

        return self::SUCCESS;
    }

    private function listWordCompletions(): int
    {
        $query = WordCompletion::query()
            ->with(['wallet', 'word.week'])
            ->orderBy('completed_at', 'desc');

        if ($this->option('week') !== null) {
            $week = Week::query()->where('number', (int) $this->option('week'))->first();
            if ($week === null) {
                $this->error('Week not found.');

                return self::FAILURE;
            }
            $query->whereHas('word', fn ($q) => $q->where('week_id', $week->id));
        }

        $completions = $query->limit(500)->get();

        if ($completions->isEmpty()) {
            $this->info('No word completions yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['week', 'pos', 'wallet', 'completed_at'],
            $completions->map(fn (WordCompletion $c): array => [
                $c->word?->week?->number,
                $c->word?->position,
                $c->wallet?->address,
                $c->completed_at?->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }
}
