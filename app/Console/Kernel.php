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
        if (config('queue.default') === 'database') {
            $schedule->command('queue:work database --queue=notifications,documents,default --stop-when-empty --max-time=50 --sleep=1 --tries=3')
                ->everyMinute()
                ->withoutOverlapping(5);
        }

        $schedule->command('exchange-rates:refresh')
            ->hourly()
            ->withoutOverlapping();
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
