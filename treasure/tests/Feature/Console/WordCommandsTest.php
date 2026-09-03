<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Week;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WordCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_word_set_creates_and_normalizes_answer(): void
    {
        $week = Week::create(['number' => 1, 'starts_at' => now()]);

        $this->artisan('treasure:word:set', [
            'week' => 1,
            'position' => 1,
            '--answer' => 'Jolly-Roger!',
            '--hint' => "Pirate's flag",
        ])->assertExitCode(0);

        $word = Word::query()->where('week_id', $week->id)->firstOrFail();
        $this->assertSame('jollyroger', $word->answer_normalized);
        $this->assertSame("Pirate's flag", $word->hint);
    }

    public function test_word_set_upserts_existing_position(): void
    {
        $week = Week::create(['number' => 1, 'starts_at' => now()]);
        Word::create(['week_id' => $week->id, 'position' => 1, 'answer_normalized' => 'old', 'hint' => 'old']);

        $this->artisan('treasure:word:set', [
            'week' => 1,
            'position' => 1,
            '--answer' => 'newanswer',
            '--hint' => 'new hint',
        ])->assertExitCode(0);

        $this->assertSame(1, Word::query()->where('week_id', $week->id)->count());
        $this->assertSame('newanswer', Word::query()->where('week_id', $week->id)->first()->answer_normalized);
    }

    public function test_word_set_fails_without_week(): void
    {
        $this->artisan('treasure:word:set', ['week' => 99, 'position' => 1, '--answer' => 'x'])
            ->assertExitCode(1);
    }

    public function test_word_list_runs(): void
    {
        $week = Week::create(['number' => 1, 'starts_at' => now()]);
        Word::create(['week_id' => $week->id, 'position' => 1, 'answer_normalized' => 'x', 'hint' => 'h']);
        $this->artisan('treasure:word:list', ['week' => 1])->assertExitCode(0);
    }
}
