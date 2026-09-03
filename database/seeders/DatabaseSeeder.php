<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Stake history is reconstructed from chain via `php artisan staking:backfill`.
    }
}
