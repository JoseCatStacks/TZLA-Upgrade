<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WeekCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_create_persists_a_week(): void
    {
        $this->artisan('treasure:week:create', [
            'number' => 1,
            '--starts-at' => '2026-01-01',
            '--title' => 'Bones',
            '--reward' => '10 TZLA',
        ])->assertExitCode(0);

        $week = Week::query()->where('number', 1)->firstOrFail();
        $this->assertSame('Bones', $week->title);
        $this->assertSame('10 TZLA', $week->reward_description);
    }

    public function test_week_create_rejects_duplicate_number(): void
    {
        Week::create(['number' => 1, 'starts_at' => now()]);
        $this->artisan('treasure:week:create', [
            'number' => 1,
            '--starts-at' => 'now',
            '--title' => 'x',
            '--reward' => 'x',
        ])->assertExitCode(1);
    }

    public function test_week_update_changes_reward(): void
    {
        Week::create(['number' => 3, 'starts_at' => now(), 'reward_description' => 'old']);
        $this->artisan('treasure:week:update', [
            'number' => 3,
            '--reward' => 'new booty',
        ])->assertExitCode(0);

        $this->assertSame('new booty', Week::query()->where('number', 3)->first()->reward_description);
    }

    public function test_week_delete_removes_row(): void
    {
        Week::create(['number' => 4, 'starts_at' => now()]);
        $this->artisan('treasure:week:delete', ['number' => 4, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Week::query()->where('number', 4)->exists());
    }

    public function test_week_list_runs_when_empty(): void
    {
        $this->artisan('treasure:week:list')->assertExitCode(0);
    }
}
