<?php

namespace App\Jobs\Pricing;

use App\Models\ServiceZone;
use App\Services\Pricing\SurgePricingEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 14 — Job de recalcul du surge pour toutes les zones actives.
 *
 * Lancé toutes les 60s (config surge.recompute_every_seconds) par le scheduler.
 *
 * Pour chaque zone active : recompute → upsert pricing_zones_state.
 *
 * Si une zone a 0 booking sur la lookback window ET 0 prestataire offline,
 * on ne calcule PAS (le state restera à expires_at < now() → multiplier 1.0
 * par defaut au prochain calcul de prix).
 */
class RecomputeSurgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public ?int $serviceZoneId = null,
    ) {}

    public function handle(SurgePricingEngine $engine): void
    {
        $query = ServiceZone::where('status', 'active');

        if ($this->serviceZoneId !== null) {
            $query->where('id', $this->serviceZoneId);
        }

        $zones = $query->get();

        $count = 0;
        foreach ($zones as $zone) {
            try {
                $engine->recomputeForZone($zone);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('RecomputeSurgeJob: échec sur zone', [
                    'zone_id' => $zone->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        Log::info('RecomputeSurgeJob: terminé', [
            'zones_processed' => $count,
            'total_zones'     => $zones->count(),
        ]);
    }

    public function tags(): array
    {
        return [
            'pricing',
            'surge',
            $this->serviceZoneId ? "zone-{$this->serviceZoneId}" : 'all-zones',
        ];
    }
}
