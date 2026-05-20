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
        $schedule->command('presence:cleanup')->everyMinute()->withoutOverlapping();
        $schedule->command('presence:scan-stale --threshold=5')->everyTwoMinutes()->withoutOverlapping();
        $schedule->command('surge:recompute')->everyMinute()->withoutOverlapping();
        $schedule->command('gdpr:enforce-retention')->dailyAt('04:00')->withoutOverlapping();
        $schedule->command('gdpr:execute-erasure-requests')->dailyAt('04:30')->withoutOverlapping();
        $schedule->command('ops:check-providers --strict')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('subscriptions:tick --limit=500')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('accounting:close-previous-month')->monthlyOn(6, '04:00')->withoutOverlapping();
        $schedule->command('fleet:scan-expiring')->dailyAt('05:00')->withoutOverlapping();

        // Spatie Backup — daily backup + monitoring + cleanup
        if (class_exists(\Spatie\Backup\BackupServiceProvider::class)) {
            $schedule->command('backup:clean')->dailyAt('01:00')->withoutOverlapping();
            $schedule->command('backup:run')->dailyAt('01:30')->withoutOverlapping();
            $schedule->command('backup:monitor')->dailyAt('07:00')->withoutOverlapping();
        }


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
