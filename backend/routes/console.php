<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:scan-sla-alerts')->everyFifteenMinutes()->withoutOverlapping(20)->onOneServer();
Schedule::command('tickets:scan-inactivity')->everyFifteenMinutes()->withoutOverlapping(20)->onOneServer();
Schedule::command('public-history:cleanup')->hourly()->withoutOverlapping(20)->onOneServer();
Schedule::command('public-actions:cleanup')->hourly()->withoutOverlapping(20)->onOneServer();
Schedule::command('idempotency:cleanup')->daily()->withoutOverlapping(20)->onOneServer();
