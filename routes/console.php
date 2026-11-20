<?php

use Illuminate\Support\Facades\Schedule;

// Requires the scheduler cron entry (see docs/DEPLOYMENT.md).
Schedule::command('portfolio:prune')->dailyAt('03:00')->withoutOverlapping();
