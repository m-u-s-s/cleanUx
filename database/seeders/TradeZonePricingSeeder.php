<?php

namespace Database\Seeders;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use Illuminate\Database\Seeder;

/** LA GRILLE COMPLÈTE : chaque métier actif × chaque zone active. */
class TradeZonePricingSeeder extends Seeder
{
    public function run(): void
    {
        $zones = ServiceZone::query()
            ->whereIn('status', ['active', 'paused'])
            ->get();

        if ($zones->isEmpty()) {
            $this->command?->warn('⚠️ Aucune zone de service : TradeZonePricingSeeder ignoré.');

            return;
        }

        $trades = Trade::query()->where('is_active', true)->get();

        if ($trades->isEmpty()) {
            $this->command?->warn('⚠️ Aucun métier actif : TradeZonePricingSeeder ignoré.');

            return;
        }

        $creees = 0;

        foreach ($zones as $zone) {
            foreach ($trades as $trade) {
                $existante = TradeZonePricing::query()
                    ->where('trade_id', $trade->id)
                    ->where('service_zone_id', $zone->id)
                    ->first();

                if ($existante) {
                    continue;
                }

                TradeZonePricing::create([
                    'trade_id' => $trade->id,
                    'service_zone_id' => $zone->id,
                    'base_rate_cents' => (int) ($trade->base_price_cents ?? 0),
                    // La colonne est un décimal : la remplir avec un float PHP la ferait arrondir
                    // au hasard de la conversion. On écrit la chaîne que la base attend.
                    'surge_multiplier' => '1.00',
                    'is_active' => true,
                    'asap_enabled' => (bool) $trade->allows_asap,
                ]);

                $creees++;
            }
        }

        $this->command?->info(sprintf(
            '✅ Grille métier × zone : %d ligne(s) créée(s) sur %d zone(s) et %d métier(s).',
            $creees,
            $zones->count(),
            $trades->count(),
        ));
    }
}
