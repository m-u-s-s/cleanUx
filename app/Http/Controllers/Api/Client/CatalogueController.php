<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\I18n\LocaleResolver;
use App\Services\OrderEngine\CatalogueServable;
use App\Support\Domain\OrderMode;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LE CATALOGUE NATIF DE L'APPLICATION CLIENTE.
 *
 * Il ne décide rien : ce qui est commandable, la zone et le prix plancher viennent de
 * `CatalogueServable`, que le parcours web lit aussi. « Plusieurs services » n'a pas de catalogue
 * natif — il reste servi par la vue web.
 */
class CatalogueController extends Controller
{
    public function __invoke(Request $request, CatalogueServable $catalogue, LocaleResolver $langues): JsonResponse
    {
        $mode = $request->validate([
            'mode' => ['required', 'string', Rule::in([OrderMode::ASAP, OrderMode::SCHEDULED])],
        ])['mode'];

        $zoneId = $catalogue->zoneDuClient($request->user());
        $langue = $langues->resolveFromRequest($request);

        $secteurs = $catalogue->secteurs($mode, $zoneId)
            ->with([
                'translations',
                // Contrainte d'un chargement anticipé : la fermeture reçoit la RELATION.
                'trades' => fn (Relation $relation) => $catalogue
                    ->contraindreLesMetiers($relation->getQuery(), $mode, $zoneId)
                    ->orderBy('sort_order')
                    ->with('translations'),
            ])
            ->get();

        // Les lignes de zone en UNE requête, jamais une par métier.
        $lignes = $zoneId === null
            ? collect()
            : TradeZonePricing::query()
                ->where('service_zone_id', $zoneId)
                ->whereIn('trade_id', $secteurs->flatMap->trades->pluck('id'))
                ->get()
                ->keyBy('trade_id');

        return response()->json([
            'mode' => $mode,
            'zone_known' => $zoneId !== null,
            'currency' => $catalogue->devise($zoneId),
            'sectors' => $secteurs->map(fn (Sector $secteur) => [
                'slug' => $secteur->slug,
                'name' => $secteur->translate('name', $langue),
                'icon' => $secteur->icon,
                'trades' => $secteur->trades->map(function (Trade $metier) use ($catalogue, $lignes, $langue, $mode) {
                    $plancher = $catalogue->plancher($metier, $lignes->get($metier->id), $mode);

                    return [
                        'slug' => $metier->slug,
                        'name' => $metier->translate('name', $langue),
                        'icon' => $metier->icon,
                        'short_description' => $metier->translate('short_description', $langue),
                        'floor_price_cents' => $plancher['cents'],
                        // Par heure quand le plancher EST le prix d'une heure — pas selon le seul drapeau du métier.
                        'hourly' => $plancher['horaire'],
                    ];
                })->values()->all(),
            ])->values()->all(),
        ]);
    }
}
