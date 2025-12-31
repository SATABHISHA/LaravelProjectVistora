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
        // Process monthly leave credits on the 1st of every month at 00:05 AM
        $schedule->command('leave:process-monthly-credits')
            ->monthlyOn(1, '00:05')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/leave-credits-cron.log'))
            ->emailOutputOnFailure(env('ADMIN_EMAIL'));

        // Alternative: Run daily and let the command handle month logic
        // $schedule->command('leave:process-monthly-credits')
        //     ->dailyAt('00:05')
        //     ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
