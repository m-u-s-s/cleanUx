<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Le catalogue d'UNE zone, servi à l'application mobile.
 *
 * POURQUOI CE CONTRÔLEUR PLUTÔT QUE LE MOTEUR DE CONSOLE. Le moteur rend n'importe quel domaine
 * décrit par un descripteur — une liste de lignes d'une table. Or l'état demandé ici n'appartient à
 * AUCUNE table à lui seul : « ce métier est-il ouvert dans cette zone » est le couple
 * `(métier, zone)`, et un métier sans ligne est fermé plutôt qu'absent. Une liste de
 * `trade_zone_pricing` montrerait les métiers déjà réglés et tairait tous les autres — exactement
 * ceux qu'on vient ouvrir.
 *
 * L'ABSENCE DE LIGNE EST UN ÉTAT. On rend donc TOUS les métiers actifs, chacun avec son
 * `is_open`, plutôt que de laisser le client déduire « fermé » d'un métier manquant.
 */
class ZoneCatalogController extends Controller
{
    /** Les métiers de la plateforme, avec leur état dans cette zone. */
    public function trades(ServiceZone $zone): JsonResponse
    {
        $ouvertures = TradeZonePricing::query()
            ->where('service_zone_id', $zone->id)
            ->get()
            ->keyBy('trade_id');

        $metiers = Trade::query()
            ->with('sector:id,name,sort_order')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Trade $metier) use ($ouvertures) {
                $ligne = $ouvertures->get($metier->id);

                return [
                    'id' => $metier->id,
                    'name' => $metier->name,
                    'slug' => $metier->slug,
                    'sector' => $metier->sector?->name,
                    'sector_id' => $metier->sector_id,
                    'is_open' => $ligne !== null && (bool) $ligne->is_active,
                    // Le tarif de la zone s'il existe, sinon celui du métier : l'écran doit
                    // montrer ce que paiera le client, pas un zéro qui n'a jamais été décidé.
                    'base_rate_cents' => (int) ($ligne !== null ? $ligne->base_rate_cents : ($metier->base_price_cents ?? 0)),
                    'has_zone_price' => $ligne !== null,
                    /*
                     * L'IMMÉDIAT, ZONE PAR ZONE — la même donnée que l'écran web.
                     *
                     * `allows_asap` dit qu'un dépannage est possible pour ce métier ; `asap_enabled`
                     * dit qu'on l'a ouvert ICI. La console mobile a besoin des deux pour savoir
                     * quand griser le bouton plutôt que de laisser ouvrir l'immédiat sur un
                     * ravalement de façade.
                     */
                    'allows_asap' => (bool) $metier->allows_asap,
                    'asap_enabled' => $ligne !== null && (bool) $ligne->asap_enabled,
                ];
            });

        return response()->json([
            'ok' => true,
            'zone' => ['id' => $zone->id, 'name' => $zone->name, 'country_id' => $zone->country_id],
            'data' => $metiers->values()->all(),
        ]);
    }

    /**
     * Ouvre ou ferme un métier dans cette zone.
     *
     * Mêmes règles que l'écran web, et c'est délibéré : éteindre ne supprime jamais la ligne, pour
     * que rallumer retrouve le tarif saisi plutôt que de repartir de zéro.
     */
    public function toggle(ServiceZone $zone, Trade $trade): JsonResponse
    {
        /*
         * `EnforcesApiAdmin` s'arrête à « est-ce un administrateur » : un compte en lecture seule
         * le franchit. La décision d'ouvrir un métier engage le prix et la disponibilité — la même
         * Policy que le web doit donc s'appliquer ici.
         */
        if (! Gate::allows('update', Trade::class)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden_readonly',
                'error_code' => 'forbidden_readonly',
            ], 403);
        }

        $ligne = TradeZonePricing::query()->firstOrNew([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
        ]);

        if (! $ligne->exists) {
            $ligne->base_rate_cents = (int) ($trade->base_price_cents ?? 0);
            $ligne->surge_multiplier = '1.00';
            $ligne->is_active = true;
        } else {
            $ligne->is_active = ! $ligne->is_active;
        }

        $ligne->save();

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $trade->id,
                'is_open' => (bool) $ligne->is_active,
                'base_rate_cents' => (int) $ligne->base_rate_cents,
                'asap_enabled' => (bool) $ligne->asap_enabled,
            ],
        ]);
    }

    /**
     * Ouvre ou ferme l'INTERVENTION IMMÉDIATE pour ce métier dans cette zone.
     *
     * Mêmes règles que l'écran web : la décision est locale, et elle exige que le métier soit
     * ouvert ici. Un métier fermé dans la zone n'y est pas vendu du tout — lui ouvrir l'immédiat
     * promettrait un dépannage pour un service absent du parcours client.
     */
    public function toggleAsap(ServiceZone $zone, Trade $trade): JsonResponse
    {
        if (! Gate::allows('update', Trade::class)) {
            return response()->json([
                'ok' => false,
                'error' => 'forbidden_readonly',
                'error_code' => 'forbidden_readonly',
            ], 403);
        }

        $ligne = TradeZonePricing::query()
            ->where('trade_id', $trade->id)
            ->where('service_zone_id', $zone->id)
            ->first();

        if (! $ligne || ! $ligne->is_active) {
            return response()->json([
                'ok' => false,
                'error' => 'trade_closed_in_zone',
                'error_code' => 'trade_closed_in_zone',
                'message' => 'Ouvrez d’abord ce métier dans la zone.',
            ], 422);
        }

        if (! $trade->allows_asap) {
            return response()->json([
                'ok' => false,
                'error' => 'trade_forbids_asap',
                'error_code' => 'trade_forbids_asap',
                'message' => 'Ce métier n’autorise pas l’intervention immédiate.',
            ], 422);
        }

        $ligne->update(['asap_enabled' => ! $ligne->asap_enabled]);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $trade->id,
                'is_open' => (bool) $ligne->is_active,
                'asap_enabled' => (bool) $ligne->asap_enabled,
            ],
        ]);
    }
}
