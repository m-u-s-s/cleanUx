<?php

namespace App\Console\Commands;

use App\Jobs\Pricing\RecomputeSurgeJob;
use App\Services\Pricing\SurgePricingEngine;
use Illuminate\Console\Command;

/** Phase 14 — Recalcule le surge pricing. */
class RecomputeSurgeCommand extends Command
{
    protected $signature = 'surge:recompute {--zone= : ID d\'une zone spécifique}';

    protected $description = 'Recalcule le surge pricing pour les zones actives';

    public function handle(): int
    {
        $zoneId = $this->option('zone');

        // Dispatch synchronisé : on attend la fin pour avoir un retour direct
        // Pour async, remplacer par RecomputeSurgeJob::dispatch(...)
        (new RecomputeSurgeJob($zoneId ? (int) $zoneId : null))->handle(
            app(SurgePricingEngine::class)
        );

        $this->info('✓ Surge pricing recalculé.');

        return self::SUCCESS;
    }
}
