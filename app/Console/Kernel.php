<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\BackfillMissionDestinations::class,
        
    ];
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:send-rendezvous-reminders')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('app:prune-read-notifications --days=30')->dailyAt('02:30')->withoutOverlapping();
        $schedule->command('google-calendar:sync --future-days=30')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('finance:sync-documents')->hourly()->withoutOverlapping();
        $schedule->command('finance:sync-documents --reminders')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('subscriptions:generate')->daily();
        $schedule->command('app:send-smart-rdv-notifications')->everyFifteenMinutes();
        $schedule->command('currencies:refresh')->dailyAt('06:00');
        

        $schedule->command('app:ops-heartbeat')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('app:production-health-check')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/production-health.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
