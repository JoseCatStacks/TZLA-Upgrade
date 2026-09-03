<?php

use App\Jobs\RefreshPoolOracle;
use Illuminate\Support\Facades\Schedule;

// Warm pool + reward vault + Jupiter price. The job itself is cheap and
// cached; a few-second cadence keeps the public pool-stats panel fresh.
Schedule::job(new RefreshPoolOracle)->everyFiveSeconds();
