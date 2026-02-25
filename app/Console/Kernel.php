<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * These schedules are run in a default environment.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run database backup daily at midnight
        $schedule->command('db:backup')
            ->dailyAt('00:00')
            ->appendOutputTo(storage_path('logs/backup.log'))
            ->onFailure(function () {
                Log::error('Scheduled database backup failed');
            })
            ->onSuccess(function () {
                Log::info('Scheduled database backup completed successfully');
            });

        // Clean up old logs daily at 1 AM
        $schedule->command('logs:cleanup')
            ->dailyAt('01:00')
            ->appendOutputTo(storage_path('logs/cleanup.log'))
            ->onFailure(function () {
                Log::error('Scheduled logs cleanup failed');
            })
            ->onSuccess(function () {
                Log::info('Scheduled logs cleanup completed successfully');
            });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
} 