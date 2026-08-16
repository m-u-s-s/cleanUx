<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraft;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\Booking\ZoneCoverageService;
use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Support\Facades\Log;

/**
 * OÙ, COMBIEN, ET EN IMMÉDIAT OU NON — une seule ligne répond aux trois.
 *
 * `trade_zone_pricing` est la source unique : ACTIVATION et PRIX sont la même ligne `(métier,
 * zone)`. Trois chemins de prix par zone coexistaient et aucun n'alimentait le parcours de
 * commande — `PricingEngine` acceptait un `zone_multiplier` que personne ne fournissait, et il
 * valait donc 1,0 partout. Le catalogue disait « ce métier coûte plus cher à Bruxelles » et le
 * client payait le tarif de base.
 *
 * ÉTEINDRE NE SUPPRIME JAMAIS LA LIGNE. Rallumer doit retrouver le tarif saisi : ce tarif a pu
 * demander une négociation qu'on ne veut pas refaire.
 *
 * L'ABSENCE DE LIGNE EST UN ÉTAT — « fermé », pas « inconnu ». Un métier sans ligne dans une zone
 * n'y est pas vendu.
 */
class ZonePricingResolver
{
    public function __construct(
        protected ZoneCoverageService $coverage,
    ) {}

    /** La ligne (métier, zone), ou `null` si le métier n'est pas ouvert dans cette zone. */
    public function lineFor(int $tradeId, ?int $zoneId): ?TradeZonePricing
    {
        if (! $zoneId) {
            return null;
        }

        return TradeZonePricing::query()
            ->where('trade_id', $tradeId)
            ->where('service_zone_id', $zoneId)
            ->where('is_active', true)
            ->first();
    }

    /** Le métier est-il vendu dans cette zone ? */
    public function isOpen(int $tradeId, ?int $zoneId): bool
    {
        return $this->lineFor($tradeId, $zoneId) !== null;
    }

    /**
     * Le métier accepte-t-il l'INTERVENTION IMMÉDIATE ici ?
     *
     * La décision est prise par zone, pas globalement : un plombier de garde à Bruxelles n'implique
     * pas un plombier de garde à Bastogne. Le drapeau global `trades.allows_asap` reste le premier
     * verrou — un ravalement de façade n'est jamais un service immédiat, nulle part.
     */
    public function allowsImmediate(Trade $trade, ?int $zoneId): bool
    {
        if (! $trade->allows_asap) {
            return false;
        }

        $line = $this->lineFor((int) $trade->id, $zoneId);

        return $line !== null && (bool) $line->asap_enabled;
    }

    /**
     * Le contexte de prix à passer au moteur de calcul.
     *
     * `base_rate_cents` REMPLACE le tarif du métier quand la zone en fixe un : c'est le sens même
     * de « l'activation et le prix sont la même ligne ». `surge_multiplier` s'applique ensuite,
     * comme les autres coefficients.
     *
     * @param  OrderDraft|null  $draft  La commande, quand elle est connue : elle seule porte la
     *                                  ROUTE mesurée, sans laquelle un tarif au kilomètre n'a rien
     *                                  à multiplier. Facultative — les appelants qui n'en ont pas
     *                                  (l'aperçu du constructeur de parcours, par exemple) obtiennent
     *                                  le contexte de zone seul, exactement comme avant.
     * @return array<string, mixed>
     */
    public function pricingContext(int $tradeId, ?int $zoneId, ?OrderDraft $draft = null): array
    {
        $line = $this->lineFor($tradeId, $zoneId);

        /*
         * La route voyage AVEC le contexte plutôt que d'être relue par le moteur.
         *
         * Le moteur tarifaire ne connaît ni panier ni réservation : lui donner à chercher la
         * distance quelque part le rattacherait à un schéma, et l'aperçu d'administration — qui
         * calcule un prix sans aucune commande — cesserait de fonctionner.
         */
        $route = $draft === null ? [] : [
            'route_distance_m' => $draft->route_distance_m,
            'route_duration_s' => $draft->route_duration_s,
        ];

        if (! $line) {
            return $route + [
                'zone_multiplier' => 1.0,
                'zone_base_cents' => null,
                'zone_min_cents' => null,
                'zone_max_cents' => null,
                'distance_pricing_enabled' => false,
                /*
                 * Sans ligne de zone, le tarif horaire du METIER fait foi. On le resout quand meme :
                 * une zone qui n'a pas encore de grille locale doit pouvoir vendre a l'heure, sinon
                 * cocher la case ne produirait aucun prix la ou personne n'a rien parametre.
                 */
                'hourly_rate_cents' => $this->tarifHoraire($tradeId, null),
            ];
        }

        /*
         * Lecture par `getAttribute` plutôt que par propriété : les casts `integer` du modèle
         * transforment un NULL de base en `0`, et un plancher à zéro euro n'est pas la même chose
         * qu'un plancher absent — le premier écraserait toute estimation.
         */
        $min = $line->getRawOriginal('min_price_cents');
        $max = $line->getRawOriginal('max_price_cents');

        /*
         * Même précaution que pour le plancher : `price_per_km_cents` n'est PAS casté en entier sur
         * le modèle, précisément pour que « aucun tarif au kilomètre » reste distinct de « zéro
         * centime le kilomètre ». Le premier laisse le forfait décider, le second facturerait la
         * distance gratuitement.
         */
        $parKm = $line->getRawOriginal('price_per_km_cents');
        $parMinute = $line->getRawOriginal('price_per_minute_cents');

        return $route + [
            'zone_multiplier' => (float) ($line->surge_multiplier ?: 1.0),
            'zone_base_cents' => (int) $line->base_rate_cents,
            'zone_min_cents' => $min === null ? null : (int) $min,
            'zone_max_cents' => $max === null ? null : (int) $max,
            'distance_pricing_enabled' => (bool) $line->distance_pricing_enabled,
            'pickup_fee_cents' => (int) $line->pickup_fee_cents,
            'price_per_km_cents' => $parKm === null ? null : (int) $parKm,
            'price_per_minute_cents' => $parMinute === null ? null : (int) $parMinute,
            'included_km' => (int) $line->included_km,
            'hourly_rate_cents' => $this->tarifHoraire($tradeId, $zoneId),
        ];
    }

    /**
     * Le tarif horaire applicable, delegue a la source unique.
     *
     * Il ne se resout PAS ici a la main : `HourlyRateResolver` porte deja la regle « la zone
     * surcharge le metier » et la nuance entre « aucune surcharge » et « une heure offerte ».
     * La recopier produirait deux verites sur le meme prix.
     */
    protected function tarifHoraire(?int $tradeId, ?int $zoneId): ?int
    {
        if ($tradeId === null) {
            return null;
        }

        $trade = Trade::query()->find($tradeId);

        if ($trade === null) {
            return null;
        }

        return app(HourlyRateResolver::class)->tarifCatalogue($trade, $zoneId);
    }

    /**
     * LA ZONE DU PANIER, RÉSOLUE UNE BONNE FOIS — et écrite dessus.
     *
     * Trois sources, dans cet ordre : ce que le parcours a déjà résolu, le code postal enregistré,
     * puis la position. Le dernier recours est la COUVERTURE NATIONALE quand la plateforme en
     * déclare une : refuser une commande dans un pays qu'on dit desservir entièrement serait un
     * refus qu'aucun exploitant n'a décidé.
     *
     * ELLE EST APPELÉE À LA CONFIRMATION autant qu'à l'affichage. Un panier peut arriver ici sans
     * zone — recomposé depuis une clé de rattrapage, créé par l'API, converti depuis un rendez-vous.
     * Le laisser passer sans zone donnerait au dispatch une réservation qu'il ne sait pas servir ;
     * le refuser sans avoir essayé de résoudre perdrait une commande servable.
     */
    public function ensureZoneFor(OrderDraft $draft): ?ServiceZone
    {
        if ($draft->service_zone_id) {
            return ServiceZone::find($draft->service_zone_id);
        }

        $zone = $this->resolveZone($draft->postal_code);

        if (! $zone && $draft->lat !== null && $draft->lng !== null) {
            $zone = $this->resolveZoneFromPosition((float) $draft->lat, (float) $draft->lng, $draft);
        }

        $zone ??= $this->nationalCoverage();

        if ($zone) {
            $draft->update(['service_zone_id' => $zone->id]);
        }

        return $zone;
    }

    /**
     * La zone d'une POSITION : on nomme d'abord, on résout ensuite.
     *
     * Le code postal trouvé est retenu sur le panier — il servira à la réservation, puis à la
     * facture. Le perdre obligerait à re-géocoder à chaque étape suivante.
     */
    public function resolveZoneFromPosition(float $lat, float $lng, ?OrderDraft $draft = null): ?ServiceZone
    {
        try {
            $result = app(GeocodingService::class)->reverseGeocode($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('[order_engine] position non nommable pour la zone', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $result?->postalCode) {
            return null;
        }

        $draft?->update(['postal_code' => $result->postalCode]);

        return $this->resolveZone($result->postalCode, $result->locality);
    }

    /**
     * La couverture nationale, si la plateforme en déclare une.
     *
     * C'est le filet : un exploitant qui a créé une zone « tout le pays » a décidé d'y intervenir
     * partout, et une adresse mal indexée ne doit pas contredire cette décision.
     */
    public function nationalCoverage(): ?ServiceZone
    {
        return ServiceZone::query()
            ->where('coverage_type', 'national')
            ->where('status', 'active')
            ->where('is_bookable', true)
            ->orderBy('priority')
            ->first();
    }

    /**
     * La zone qui dessert cette adresse.
     *
     * Le code postal d'abord — c'est la donnée que le client saisit et celle que le pivot
     * `service_zone_postal_code` indexe. Le repli par province, région puis couverture nationale
     * vit dans `ZoneCoverageService` et n'est pas réécrit ici.
     */
    public function resolveZone(?string $postalCode, ?string $city = null): ?ServiceZone
    {
        if (blank($postalCode)) {
            return null;
        }

        try {
            $postal = $this->coverage->resolvePostalCode($postalCode, $city);

            return $postal ? $this->coverage->resolveServiceZone($postal, null, true) : null;
        } catch (\Throwable $e) {
            Log::warning('[order_engine] résolution de zone impossible', [
                'postal_code' => $postalCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
