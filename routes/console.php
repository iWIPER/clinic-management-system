<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('patients:update-auto-status')->dailyAt('03:00');
Schedule::command('referrals:process-eligibility')->dailyAt('04:00');
Schedule::command('documents:expire-signature-tokens')->everyFifteenMinutes();
