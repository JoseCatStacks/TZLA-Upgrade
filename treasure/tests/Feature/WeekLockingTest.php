<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Week;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WeekLockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_week_show_returns_403(): void
    {
        Week::create([
            'number' => 1,
            'starts_at' => now()->addDay(),
        ]);

        $this->getJson('/api/weeks/1')->assertStatus(403);
    }

    public function test_unlocked_week_show_returns_words(): void
    {
        $week = Week::create([
            'number' => 1,
            'title' => 'Bones',
            'starts_at' => now()->subDay(),
            'reward_description' => '10 TZLA',
        ]);
        Word::create(['week_id' => $week->id, 'position' => 1, 'answer_normalized' => 'x', 'hint' => 'h1']);
        Word::create(['week_id' => $week->id, 'position' => 2, 'answer_normalized' => 'y', 'hint' => 'h2']);

        $this->getJson('/api/weeks/1')
            ->assertOk()
            ->assertJsonPath('number', 1)
            ->assertJsonPath('is_unlocked', true)
            ->assertJsonCount(2, 'words');
    }

    public function test_weeks_index_returns_all_weeks(): void
    {
        Week::create(['number' => 1, 'starts_at' => now()->subDay()]);
        Week::create(['number' => 2, 'starts_at' => now()->addDay()]);
        Week::create(['number' => 6, 'starts_at' => now()->subDay()]);

        $res = $this->getJson('/api/weeks')->assertOk()->json();
        $this->assertCount(3, $res['weeks']);
        $this->assertTrue($res['weeks'][0]['is_unlocked']);
        $this->assertFalse($res['weeks'][1]['is_unlocked']);
    }

    public function test_missing_week_returns_404(): void
    {
        $this->getJson('/api/weeks/99')->assertStatus(404);
    }
}
