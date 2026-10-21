<?php

use App\Services\Diagnostics\Heartbeats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('openlink:prune-analytics')->dailyAt('03:15');
Schedule::command('openlink:verify-pending-domains')->hourly();
Schedule::command('openlink:heartbeat')->cron(app(Heartbeats::class)->schedulerCron());
Schedule::command('openlink:monitor-destinations')->everyFifteenMinutes()->withoutOverlapping();
