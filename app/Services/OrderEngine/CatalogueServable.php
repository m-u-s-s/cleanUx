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

    /**
     * La contrainte seule, pour un `withCount` ou un `whereHas`.
     *
     * Générique : la requête d'une relation arrive typée `Builder<Model>` (le modèle lié d'une relation
     * nommée par une chaîne ne se déduit pas), celle de `metiers()` en `Builder<Trade>` ; chacune
     * repart avec son propre type.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function contraindreLesMetiers(Builder $query, ?string $mode, ?int $zoneId): Builder
    {
        return $query->where('is_active', true)->servableEnMode($mode, $zoneId);
    }

    /**
     * LA ZONE QUE LE PARCOURS WEB RETIENDRAIT pour ce client — lue dans le même ordre que lui.
     *
     * `OrderJourney::mount` reprend d'abord le dernier panier ouvert du client et en recopie
     * l'adresse et la zone ; il ne pré-remplit depuis le lieu par défaut QUE si ce panier n'a pas
     * d'adresse. Lire le lieu par défaut seul annoncerait la grille d'une zone pendant que la
     * commande, ouverte par « Commander », en chiffre une autre.
     *
     * Le panier est lu, jamais créé : consulter le catalogue n'ouvre pas de commande.
     */
    public function zoneDuClient(User $client): ?int
    {
        $panier = app(OrderDraftManager::class)->dernierPanierOuvert($client);

        if ($panier !== null && trim((string) $panier->address) !== '') {
            return $panier->service_zone_id === null ? null : (int) $panier->service_zone_id;
        }

        $zone = $this->lieux->parDefaut($client)?->service_zone_id;

        return $zone === null ? null : (int) $zone;
    }

    /**
     * LE PLANCHER ANNONCÉ AVANT DEVIS — le minimum que le moteur de commande calcule lui-même.
     *
     * On appelle `PricingEngine` plutôt que d'écrire une règle à côté de lui : « tarif de zone,
     * sinon prix du métier » ignorait la majoration de l'immédiat, le coefficient et le plancher de
     * la zone, et l'application annonçait « dès 85 € » une plomberie que le parcours web, ouvert par
     * « Commander », estimait à 111–127 €. Une seule source de vérité : le contexte est celui que le
     * web passe au moteur avant toute réponse — même ligne active, même mode —, si bien que le
     * chiffre annoncé ici est celui que le devis affiche en premier.
     *
     * Un métier horaire annonce le prix d'UNE heure, lu « dès X/h ». Un métier au devis obligatoire
     * n'annonce rien, pas plus que le moteur.
     *
     * @return array{cents: int|null, horaire: bool}
     */
    public function plancher(Trade $trade, ?TradeZonePricing $ligne, string $mode): array
    {
        // `ZonePricingResolver::lineFor` ne voit que les lignes actives : une ligne inactive vaut une absence de ligne.
        $ligneActive = $ligne?->is_active ? $ligne : null;
        $contexte = ['mode' => $mode] + app(ZonePricingResolver::class)->contexteDeLaLigne($trade, $ligneActive);
        $moteur = app(PricingEngine::class);

        if ($trade->hourly_billing && $contexte['hourly_rate_cents'] !== null) {
            $uneHeure = $moteur->quoteItem($trade, collect(), [], $contexte + ['purchased_minutes' => 60]);

            return ['cents' => $uneHeure->minCents > 0 ? $uneHeure->minCents : null, 'horaire' => true];
        }

        $devis = $moteur->quoteItem($trade, collect(), [], $contexte);

        if ($devis->quoteOnly || $devis->minCents <= 0) {
            return ['cents' => null, 'horaire' => false];
        }

        return ['cents' => $devis->minCents, 'horaire' => false];
    }

    public function devise(?int $zoneId): string
    {
        $zone = $zoneId === null ? null : ServiceZone::query()->with('country')->find($zoneId);

        return $zone?->deviseDeLaZone() ?? strtoupper((string) config('fx.base_currency', 'EUR'));
    }
}
