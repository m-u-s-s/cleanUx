<?php

namespace App\Console;

use App\Console\Commands\BackfillMissionDestinations;
use App\Jobs\Audit\PurgeAuditEventsJob;
use App\Jobs\Fx\RefreshFxRatesJob;
use App\Jobs\Marketing\DispatchCampaignStepJob;
use App\Jobs\Marketing\RecomputeSegmentJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\Backup\BackupServiceProvider;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        BackfillMissionDestinations::class,

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

        // Recurring bookings — create and dispatch daily due occurrences
        $schedule->command('bookings:process-recurring')->dailyAt('06:30')->withoutOverlapping();

        // NPS surveys — send post-booking surveys to eligible clients
        $schedule->command('nps:send-surveys')->dailyAt('10:00')->withoutOverlapping();

        // Provider payouts — compute commissions + Stripe Transfers for completed bookings
        $schedule->command('payouts:process')->dailyAt('02:00')->withoutOverlapping();

        // Stripe reconciliation : audit Stripe ↔ DB chaque jour
        $schedule->command('stripe:reconcile --scope=all --days=1')->dailyAt('05:30')->withoutOverlapping();

        // Audit v2 — purge old events selon retention policies
        if (class_exists(PurgeAuditEventsJob::class)) {
            $schedule->job(new PurgeAuditEventsJob)->dailyAt('03:15')->withoutOverlapping();
        }

        // Marketing v2 — dispatch des steps drip + recompute segments.
        // Laravel 11 : name() AVANT withoutOverlapping() (CallbackEvent::withoutOverlapping
        // requires $this->description set, populated by name()).
        if (class_exists(DispatchCampaignStepJob::class)) {
            $schedule->call(function () {
                DispatchCampaignStepJob::dispatch();
            })->name('marketing:dispatch-steps')->everyTenMinutes()->withoutOverlapping()->onOneServer();
        }
        if (class_exists(RecomputeSegmentJob::class)) {
            $schedule->call(function () {
                RecomputeSegmentJob::dispatch();
            })->name('marketing:recompute-segments')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
        }

        // FX rates refresh via job async (vs sync via currencies:refresh)
        if (class_exists(RefreshFxRatesJob::class)) {
            $schedule->job(new RefreshFxRatesJob)->dailyAt('06:15')->withoutOverlapping();
        }

        // Spatie Backup — daily backup + monitoring + cleanup
        if (class_exists(BackupServiceProvider::class)) {
            $schedule->command('backup:clean')->dailyAt('01:00')->withoutOverlapping();
            $schedule->command('backup:run')->dailyAt('01:30')->withoutOverlapping();
            $schedule->command('backup:monitor')->dailyAt('07:00')->withoutOverlapping();
        }

        // Backup verification — monthly integrity check
        $schedule->command('backup:verify')->monthly()->withoutOverlapping();

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
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
