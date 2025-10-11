<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check and account suspension
// Runs daily at 1:00 AM to check grace periods and suspend expired accounts
Schedule::command('subscriptions:check-expired --suspend')->dailyAt('01:00');

// Schedule monthly free slot reset for free users
// Runs on the 1st day of every month at midnight
Schedule::command('slots:reset-monthly')->monthlyOn(1, '00:00');
