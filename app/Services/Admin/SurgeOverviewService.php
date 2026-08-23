<?php

namespace App\Services\Admin;

use App\Models\TradeZonePricing;
use Illuminate\Support\Facades\Config;

/** LA CARTE DES MAJORATIONS (E28) — voir d'un coup ce que la plateforme facture en plus. */
class SurgeOverviewService
{
    /**
     * Toutes les grilles, avec leur majoration.
     *
     * @return array<string, mixed>
     */
    public function carte(): array
    {
        $plafond = (float) Config::get('surge.max_multiplier', 3.0);

        $lignes = TradeZonePricing::query()
            ->with(['trade:id,name', 'serviceZone:id,name'])
            ->get(['id', 'trade_id', 'service_zone_id', 'surge_multiplier', 'is_active', 'base_rate_cents']);

        $presentees = $lignes
            ->map(fn (TradeZonePricing $ligne) => [
                'id' => $ligne->id,
                'trade_id' => $ligne->trade_id,
                'trade_name' => $ligne->trade->name ?? 'Métier inconnu',
                'zone_id' => $ligne->service_zone_id,
                'zone_name' => $ligne->serviceZone->name ?? 'Zone inconnue',
                'multiplier' => (float) $ligne->surge_multiplier,
                'is_active' => (bool) $ligne->is_active,
                // LE DÉPASSEMENT EST SIGNALÉ, PAS CORRIGÉ.
                'exceeds_cap' => (float) $ligne->surge_multiplier > $plafond,
                'effective_multiplier' => min((float) $ligne->surge_multiplier, $plafond),
            ])
            ->sortByDesc('multiplier')
            ->values();

        $majorees = $presentees->filter(fn (array $l) => $l['multiplier'] > 1.0);

        return [
            'cap' => $plafond,
            // Le drapeau compte autant que les chiffres : coupé, tout ceci ne s'applique nulle part.
            'surge_enabled' => (bool) feature('surge_pricing'),
            'rows_count' => $presentees->count(),
            'surged_count' => $majorees->count(),
            'exceeding_cap_count' => $presentees->where('exceeds_cap', true)->count(),
            'max_multiplier_in_use' => $presentees->max('multiplier') ?? 1.0,
            // On rend TOUT, y compris les lignes à 1 : ce qu'il faut voir est la proportion.
            'rows' => $presentees->all(),
        ];
    }
}
