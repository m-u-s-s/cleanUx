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
        // Quatre echeances qu'aucun clic ne declenche : voir la commande. L'empreinte Stripe
        // ne tient que sept jours, d'ou une passe HORAIRE et non quotidienne.
        $schedule->command('peer-rental:entretenir')->hourly()->withoutOverlapping();
        $schedule->command('finance:sync-documents')->hourly()->withoutOverlapping();
        $schedule->command('finance:sync-documents --reminders')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('app:generate-subscriptions')->daily();
        $schedule->command('app:send-smart-rdv-notifications')->everyFifteenMinutes();
        $schedule->command('currencies:refresh')->dailyAt('06:00');
        $schedule->command('presence:cleanup')->everyMinute()->withoutOverlapping();
        // Les numéros proxy se louent : une session jamais fermée coûte tous les mois, et laisse
        // surtout un prestataire rappeler une cliente des semaines après l'intervention.
        $schedule->command('masked-calls:scan-expired')->hourly()->withoutOverlapping();
        $schedule->command('presence:scan-stale --threshold=5')->everyTwoMinutes()->withoutOverlapping();

        // LE FILET SOUS LES OFFRES DE MISSION.
        $schedule->command('dispatch:balayer-les-offres-expirees')->everyTwoMinutes()->withoutOverlapping();

        // LA SONDE QUI NE TOURNAIT NULLE PART.
        $schedule->command('spine:check-stuck-missions')->hourly()->withoutOverlapping();
        $schedule->command('surge:recompute')->everyMinute()->withoutOverlapping();

        // Un contrôle facial ouvert et jamais répondu bloque la réouverture du suivant : le prestataire resterait devant un écran mort.
        $schedule->command('face-check:maintenance')->everyFiveMinutes()->withoutOverlapping();

        // LE MINUTEUR DE RETARD.
        $schedule->command('missions:signaler-les-retards')->everyFiveMinutes()->withoutOverlapping();

        // Les suppléments acceptés mais jamais encaissés.
        $schedule->command('extras:reprendre-les-prelevements')->hourly()->withoutOverlapping();

        // Le temps supplémentaire constaté mais jamais encaissé.
        $schedule->command('temps:reprendre-les-reglements')->hourly()->withoutOverlapping();
        $schedule->command('gdpr:enforce-retention')->dailyAt('04:00')->withoutOverlapping();
        $schedule->command('gdpr:execute-erasures')->dailyAt('04:30')->withoutOverlapping();
        $schedule->command('ops:check-providers --strict')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->command('subscriptions:tick --limit=500')->dailyAt('03:00')->withoutOverlapping();
        $schedule->command('accounting:close-previous-month')->monthlyOn(6, '04:00')->withoutOverlapping();
        $schedule->command('fleet:scan-expiring')->dailyAt('05:00')->withoutOverlapping();
        // L'autre moitié du dossier : la flotte savait prévenir qu'un contrôle technique arrivait à terme, les pièces du prestataire — permis, assurance — ne le savaient pas.
        $schedule->command('provider:scan-expiring-documents')->dailyAt('05:15')->withoutOverlapping();
        $schedule->command('bundles:scan-quote-requests')->hourly()->withoutOverlapping();

        // SP4 — contract SLA monitor (mark met / breached + escalate once)
        $schedule->command('contract:scan-sla')->everyFifteenMinutes()->withoutOverlapping();

        // Recurring bookings — create and dispatch daily due occurrences
        $schedule->command('bookings:process-recurring')->dailyAt('06:30')->withoutOverlapping();

        /*
         * TROIS COMMANDES ECRITES, CORRECTES, ET QUE RIEN NE DECLENCHAIT.
         *
         * Mesure : elles n'etaient citees que dans leur propre fichier — ni ici, ni dans un
         * appel, ni dans la CI, ni dans un script de deploiement. Ce n'etait pas du code mort :
         * c'etait du code DEBRANCHE, et la difference tient en une ligne.
         *
         * Ce que leur absence coutait :
         *   disputes:process-sla     le delai d'un litige n'etait JAMAIS escalade.
         *   matching:refresh-metrics `provider_performance_metrics` comptait ZERO ligne, et
         *                            `MatchingScoreEngine` y lit trois de ses criteres. Le
         *                            moteur encaisse le `null` proprement, donc les scores ne
         *                            sont pas faux — mais taux d'acceptation, taux de clôture
         *                            et delai de reponse ne pesaient rien.
         *   loyalty:reevaluate-tiers les MONTEES de palier fonctionnent, elles suivent
         *                            l'activite. C'est la RETROGRADATION par inactivite qui
         *                            n'avait que cette commande pour declencheur.
         */
        $schedule->command('disputes:process-sla')->hourly()->withoutOverlapping();
        $schedule->command('matching:refresh-metrics')->dailyAt('03:30')->withoutOverlapping();
        $schedule->command('loyalty:reevaluate-tiers')->dailyAt('04:15')->withoutOverlapping();

        // NPS surveys — send post-booking surveys to eligible clients
        $schedule->command('nps:send-surveys')->dailyAt('10:00')->withoutOverlapping();

        // Provider payouts — compute commissions + Stripe Transfers for completed bookings
        $schedule->command('payouts:process')->dailyAt('02:00')->withoutOverlapping();

        // Stripe reconciliation : audit Stripe ↔ DB chaque jour
        $schedule->command('stripe:reconcile --scope=all --days=1')->dailyAt('05:30')->withoutOverlapping();

        // M9 — re-dispatch transiently-failed Stripe webhook events that are due for retry.
        $schedule->command('stripe:retry-failed-webhooks')->hourly()->withoutOverlapping();

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

        $schedule->command('automation:executer')->everyMinute()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
