<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tz = config('app.timezone', 'Asia/Jakarta');

Schedule::command('reservations:expire')
    ->everyFiveMinutes()
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:sync-item-loan-status')
    ->dailyAt('02:10')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:dashboard-alerts')
    ->dailyAt('08:00')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:sync-search-synonyms --limit=300 --min=2 --lev=2 --prefix=3 --aggressive=1')
    ->dailyAt('03:30')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

if ((bool) config('notobuku.catalog.zero_result_governance.enabled', true)) {
    Schedule::command(sprintf(
        'notobuku:search-zero-triage --limit=%d --min-search-count=%d --age-hours=%d --force-close-open=%d',
        (int) config('notobuku.catalog.zero_result_governance.limit', 500),
        (int) config('notobuku.catalog.zero_result_governance.min_search_count', 2),
        (int) config('notobuku.catalog.zero_result_governance.age_hours', 24),
        (bool) config('notobuku.catalog.zero_result_governance.force_close_open', true) ? 1 : 0
    ))
        ->dailyAt('03:40')
        ->timezone($tz)
        ->withoutOverlapping()
        ->runInBackground();
}

Schedule::command('notobuku:reminder-jatuh-tempo')
    ->dailyAt('07:30')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:member-import-snapshot')
    ->monthlyOn(1, '02:40')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-audit-snapshot')
    ->monthlyOn(1, '03:05')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-health-alert')
    ->everyFiveMinutes()
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-exception-snapshot')
    ->dailyAt('04:10')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-exception-escalation')
    ->hourly()
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-handover-report')
    ->dailyAt('04:25')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:circulation-pic-reminder')
    ->hourlyAt(15)
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:interop-reconcile')
    ->dailyAt('03:50')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:opac-slo-alert')
    ->everyFiveMinutes()
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:backup-core-snapshot --tag=auto')
    ->dailyAt('02:55')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:backup-restore-drill')
    ->monthlyOn(1, '04:45')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:uat-generate')
    ->weeklyOn(1, '05:30')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:catalog-scale-proof --samples=60')
    ->weeklyOn(1, '05:45')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notobuku:readiness-certificate --strict-ready --window-days=30')
    ->dailyAt('05:55')
    ->timezone($tz)
    ->withoutOverlapping()
    ->runInBackground();

if ((bool) config('notobuku.uat.auto_signoff.enabled', true)) {
    Schedule::command('notobuku:uat-auto-signoff --strict-ready --window-days=30')
        ->dailyAt('06:05')
        ->timezone($tz)
        ->withoutOverlapping()
        ->runInBackground();
}
