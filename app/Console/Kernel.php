<?php

namespace App\Console;

use App\Console\Commands\ExpirePendingTokenRequestsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Expire pending token requests every hour
        $schedule->command(ExpirePendingTokenRequestsCommand::class)
            ->hourly()
            ->onOneServer()
            ->name('expire-pending-token-requests')
            ->description('Mark as expired pending token requests and clean old encrypted tokens');
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
