<?php

namespace App\Services\Rental;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * QUELLES VOITURES SONT RÉELLEMENT DISPONIBLES — c'est la règle qui décide de tout le catalogue.
 *
 * Deux exigences se rejoignent ici et se répondent :
 *
 *   « ne pas afficher les voitures louées dans le catalogue »
 *   « la case Location n'est pas visible s'il n'y a aucune voiture en location »
 *
 * La seconde découle de la première : c'est le MÊME compte. Si aucune voiture n'est disponible,
 * l'entrée disparaît — pas parce qu'on l'a codée ainsi à deux endroits, mais parce qu'on pose une
 * seule question.
 *
 * ── LE CHEVAUCHEMENT, ET LE SENS DES COMPARAISONS ────────────────────────────────────────────
 *
 * Deux périodes se chevauchent quand l'une commence AVANT que l'autre ne finisse et finit APRÈS
 * que l'autre a commencé. Toute autre écriture laisse passer les locations enchâssées — celles qui
 * tiennent entièrement à l'intérieur d'une autre, c'est-à-dire le cas le plus courant d'une
 * location courte pendant une longue. La voiture serait alors louée deux fois, et personne ne le
 * verrait avant le comptoir.
 *
 * Les bornes sont STRICTES : une location qui finit à 10 h et une autre qui commence à 10 h ne se
 * chevauchent pas. C'est ce qui permet d'enchaîner deux clients sur la même journée.
 */
class RentalAvailability
{
    /**
     * Les véhicules proposables, filtrés et triés.
     *
     * @param  array{category?: string|null, transmission?: string|null, fuel?: string|null, seats_min?: int|null, price_max_cents?: int|null}  $filtres
     * @return Collection<int, RentalVehicle>
     */
    public function catalogue(?CarbonInterface $debut = null, ?CarbonInterface $fin = null, array $filtres = []): Collection
    {
        return $this->requeteDuCatalogue($debut, $fin, $filtres)
            ->with(['galerie', 'rotation360', 'pickupPoint'])
            ->get();
    }

    /**
     * Combien de voitures sont proposables — la question que pose l'entrée du catalogue.
     *
     * Une méthode dédiée plutôt qu'un `count()` sur la collection : l'entrée n'a pas besoin des
     * images ni des agences, et les charger pour n'en garder qu'un nombre ferait payer la page
     * d'accueil pour rien.
     */
    public function combienDeVehiculesProposables(?CarbonInterface $debut = null, ?CarbonInterface $fin = null): int
    {
        return $this->requeteDuCatalogue($debut, $fin)->count();
    }

    /**
     * Ce véhicule précis est-il libre sur cette période ?
     *
     * Posée au moment de confirmer, et pas seulement à l'affichage : entre le clic et la
     * validation, quelqu'un d'autre a pu réserver. C'est la seule vérification qui protège
     * réellement d'une double location.
     */
    public function estLibre(RentalVehicle $vehicule, CarbonInterface $debut, CarbonInterface $fin, ?int $saufReservationId = null): bool
    {
        return ! RentalBooking::query()
            ->where('rental_vehicle_id', $vehicule->id)
            ->quiBloque()
            ->when($saufReservationId, fn (Builder $q) => $q->whereKeyNot($saufReservationId))
            ->where('starts_at', '<', $fin)
            ->where('ends_at', '>', $debut)
            ->exists();
    }

    /**
     * Les valeurs de filtre qui ont réellement des voitures derrière elles.
     *
     * ON NE PROPOSE PAS UN FILTRE QUI NE REND RIEN. Une liste de catégories tirée de la
     * configuration afficherait « monospace » sur un parc qui n'en a aucun, et le client
     * apprendrait en cliquant que la vitrine ment. Les options viennent donc du parc disponible.
     *
     * @return array{categories: list<string>, transmissions: list<string>, fuels: list<string>, prix_max_cents: int}
     */
    public function optionsDeFiltre(?CarbonInterface $debut = null, ?CarbonInterface $fin = null): array
    {
        $vehicules = $this->requeteDuCatalogue($debut, $fin)
            ->get(['category', 'transmission', 'fuel', 'daily_price_cents']);

        return [
            'categories' => $vehicules->pluck('category')->filter()->unique()->sort()->values()->all(),
            'transmissions' => $vehicules->pluck('transmission')->filter()->unique()->sort()->values()->all(),
            'fuels' => $vehicules->pluck('fuel')->filter()->unique()->sort()->values()->all(),
            'prix_max_cents' => (int) ($vehicules->max('daily_price_cents') ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * LA REQUÊTE UNIQUE DONT TOUT DÉCOULE.
     *
     * Le catalogue, le compte de l'entrée et les options de filtre posent la même question et
     * doivent donc partir d'ici. Trois copies auraient fini par diverger — et le jour où l'une dit
     * « une voiture disponible » pendant qu'une autre n'en montre aucune, le client voit une
     * vitrine vide derrière une porte qui promettait du choix.
     *
     * @param  array<string, mixed>  $filtres
     * @return Builder<RentalVehicle>
     */
    private function requeteDuCatalogue(?CarbonInterface $debut, ?CarbonInterface $fin, array $filtres = []): Builder
    {
        return RentalVehicle::query()
            ->actif()
            ->libreEntre($debut, $fin)
            ->when($filtres['category'] ?? null, fn (Builder $q, $v) => $q->where('category', $v))
            ->when($filtres['transmission'] ?? null, fn (Builder $q, $v) => $q->where('transmission', $v))
            ->when($filtres['fuel'] ?? null, fn (Builder $q, $v) => $q->where('fuel', $v))
            ->when($filtres['seats_min'] ?? null, fn (Builder $q, $v) => $q->where('seats', '>=', (int) $v))
            /*
             * ZÉRO EST UNE VALEUR, ET C'EST LE PIÈGE HABITUEL DE CE DÉPÔT.
             *
             * Un plafond de prix à zéro ne veut rien dire ici, mais l'écrire avec `when()` nu le
             * traiterait comme « pas de filtre » — ce qui est justement le comportement voulu, et
             * qu'il vaut mieux dire que laisser deviner.
             */
            ->when(($filtres['price_max_cents'] ?? 0) > 0,
                fn (Builder $q) => $q->where('daily_price_cents', '<=', (int) $filtres['price_max_cents']))
            ->ordonne();
    }
}
