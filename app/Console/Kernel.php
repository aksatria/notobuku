<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        $schedule->command('reservations:expire')
            ->everyFiveMinutes()
            ->timezone($tz)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notobuku:sync-item-loan-status')
            ->dailyAt('02:10')
            ->timezone($tz)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notobuku:dashboard-alerts')
            ->dailyAt('08:00')
            ->timezone($tz)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notobuku:sync-search-synonyms --limit=300 --min=2 --lev=2 --prefix=3 --aggressive=1')
            ->dailyAt('03:30')
            ->timezone($tz)
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notobuku:reminder-jatuh-tempo')
            ->dailyAt('07:30')
            ->timezone($tz)
            ->withoutOverlapping()
            ->runInBackground();

        // Kalau suatu saat deploy multi-instance:
        // ->onOneServer()
        //
        // Kalau mau log output:
        // ->appendOutputTo(storage_path('logs/scheduler.log'))
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
