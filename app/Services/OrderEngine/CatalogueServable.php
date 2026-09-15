<?php

namespace App\Services\OrderEngine;

use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Client\ClientPlaceService;
use Illuminate\Database\Eloquent\Builder;

/**
 * CE QUE LE MOTEUR DE COMMANDE ACCEPTE — DIT UNE SEULE FOIS.
 *
 * Le parcours web (`OrderJourney`) et le catalogue natif de l'application lisent ce filtre ici.
 * Écrit deux fois, il divergerait : l'application proposerait un métier que la commande refuse
 * ensuite, ou cacherait celui qu'elle accepte.
 */
class CatalogueServable
{
    public function __construct(protected ClientPlaceService $lieux) {}

    /** @return Builder<Sector> */
    public function secteurs(?string $mode, ?int $zoneId): Builder
    {
        return Sector::query()
            ->active()
            ->ordered()
            // UN SECTEUR SANS AUCUN MÉTIER SERVABLE N'EST PAS PROPOSÉ.
            ->whereHas('trades', fn (Builder $q) => $this->contraindreLesMetiers($q, $mode, $zoneId));
    }

    /** @return Builder<Trade> */
    public function metiers(int $sectorId, ?string $mode, ?int $zoneId): Builder
    {
        return $this->contraindreLesMetiers(Trade::query()->where('sector_id', $sectorId), $mode, $zoneId)
            ->orderBy('sort_order');
    }

    /** La contrainte seule, pour un `withCount` ou un `whereHas`. */
    public function contraindreLesMetiers(Builder $query, ?string $mode, ?int $zoneId): Builder
    {
        return $query->where('is_active', true)->servableEnMode($mode, $zoneId);
    }

    /** La zone du lieu par défaut — la même source que le pré-remplissage du parcours web. */
    public function zoneDuClient(User $client): ?int
    {
        $zone = $this->lieux->parDefaut($client)?->service_zone_id;

        return $zone === null ? null : (int) $zone;
    }

    /** Le plancher annoncé avant devis : tarif de zone, sinon prix du métier, sinon inconnu. */
    public function prixPlancherCents(Trade $trade, ?TradeZonePricing $ligneDeZone): ?int
    {
        $tarifDeZone = $ligneDeZone !== null && $ligneDeZone->is_active ? (int) $ligneDeZone->base_rate_cents : 0;

        if ($tarifDeZone > 0) {
            return $tarifDeZone;
        }

        $prixDuMetier = (int) ($trade->base_price_cents ?? 0);

        return $prixDuMetier > 0 ? $prixDuMetier : null;
    }

    public function devise(?int $zoneId): string
    {
        $zone = $zoneId === null ? null : ServiceZone::query()->with('country')->find($zoneId);

        return $zone?->deviseDeLaZone() ?? strtoupper((string) config('fx.base_currency', 'EUR'));
    }
}
