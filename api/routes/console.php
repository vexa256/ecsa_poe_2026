<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled notification jobs (Uganda POE Sentinel)
|--------------------------------------------------------------------------
|
| Keep these in sync with NotificationDispatcher. The scheduler runs inside
| Laravel's `schedule:run` task. In production, this single host cron entry
| is enough to drive every job below:
|
|     * * * * * cd /home/hacker/ecsa_poe_2026/api && php artisan schedule:run >> /dev/null 2>&1
|
| Verify schedule registration:
|     php artisan schedule:list
|
| Test a job without waiting for its cadence:
|     php artisan notifications:daily-digest
|     php artisan notifications:followup-reminders
|     php artisan notifications:retry-failed
*/

// 1) Daily national digest — 07:00 local time every morning.
Schedule::command('notifications:daily-digest')
    ->timezone('Africa/Kampala')
    ->dailyAt('07:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->description('Send the daily POE surveillance digest to all subscribed contacts.');

// 2) Follow-up reminders — every hour at :15, so new RTSL 14 actions
//    are reminded promptly without SMTP spam.
Schedule::command('notifications:followup-reminders')
    ->timezone('Africa/Kampala')
    ->hourlyAt(15)
    ->withoutOverlapping(15)
    ->onOneServer()
    ->description('Send due / overdue follow-up reminders.');

// 3) Retry failed notifications — every 15 minutes.
Schedule::command('notifications:retry-failed')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->description('Retry FAILED notification_log rows (max 4 attempts).');

// 4) National Surveillance Intelligence digest — every 3 days at 08:00 local.
//    Goes only to top-3 priority NATIONAL contacts of each country with a
//    seeded roster. Strategic briefing, not a tactical alert.
Schedule::command('notifications:national-digest')
    ->timezone('Africa/Kampala')
    ->cron('0 8 */3 * *')   // every 3 days at 08:00
    ->withoutOverlapping(120)
    ->onOneServer()
    ->description('Triennial National Intelligence digest (NATIONAL_ADMIN tier only).');
