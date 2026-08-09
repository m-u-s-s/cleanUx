<?php

namespace Database\Seeders;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use Illuminate\Database\Seeder;

/**
 * LA GRILLE COMPLÈTE : chaque métier actif × chaque zone active.
 *
 * `trade_zone_pricing` est la source unique — l'ACTIVATION et le PRIX y sont la même ligne. Une
 * plateforme fraîchement semée sans cette grille est une plateforme où AUCUN métier n'est vendu
 * nulle part : le parcours de commande refuserait toute confirmation, et personne ne comprendrait
 * pourquoi puisque les métiers, eux, existent et sont publiés.
 *
 * LE PRIX DE DÉPART EST CELUI DU MÉTIER, pas zéro. Ouvrir un métier dans une zone ne doit pas le
 * mettre à zéro euro en attendant qu'un administrateur saisisse une grille : c'est une plateforme
 * qui travaille gratuitement jusqu'à ce que quelqu'un s'en aperçoive.
 *
 * L'IMMÉDIAT SUIT LE MÉTIER, et seulement lui. `asap_enabled` n'est ouvert que là où
 * `trades.allows_asap` l'autorise : semer un dépannage sur un ravalement de façade engagerait la
 * plateforme à dépêcher quelqu'un dans l'heure pour un chantier de trois jours. Sur les métiers qui
 * l'autorisent, il est ouvert d'emblée — sans quoi la démonstration du moteur de répartition
 * n'aurait aucun métier à se mettre sous la dent, et il faudrait aller cocher une case en base
 * avant de pouvoir montrer quoi que ce soit.
 *
 * IDEMPOTENT ET NON DESTRUCTIF. Rejouer le seeder ne remet pas à zéro un tarif négocié : les
 * lignes existantes sont laissées telles quelles. C'est la même règle que l'écran d'administration,
 * où éteindre un métier ne supprime jamais sa ligne.
 */
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
