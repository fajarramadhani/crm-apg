<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:scan-sla-alerts')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('tickets:scan-inactivity')->everyFifteenMinutes()->withoutOverlapping();
