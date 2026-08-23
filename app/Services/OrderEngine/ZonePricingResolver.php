<?php

namespace App\Services\OrderEngine;

use App\Models\OrderDraft;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\Booking\ZoneCoverageService;
use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Support\Facades\Log;

/** OÙ, COMBIEN, ET EN IMMÉDIAT OU NON — une seule ligne répond aux trois. */
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

    /** Le métier accepte-t-il l'INTERVENTION IMMÉDIATE ici ? */
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

        // La route voyage AVEC le contexte plutôt que d'être relue par le moteur.
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
                // Sans ligne de zone, le tarif horaire du METIER fait foi.
                'hourly_rate_cents' => $this->tarifHoraire($tradeId, null),
            ];
        }

        // Lecture par `getAttribute` plutôt que par propriété : les casts `integer` du modèle transforment un NULL de base en `0`, et un plancher à zéro euro n'est pas la même chose qu'un plancher absent — le premier écraserait toute estimation.
        $min = $line->getRawOriginal('min_price_cents');
        $max = $line->getRawOriginal('max_price_cents');

        // Même précaution que pour le plancher : `price_per_km_cents` n'est PAS casté en entier sur le modèle, précisément pour que « aucun tarif au kilomètre » reste distinct de « zéro centime le kilomètre ».
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

    /** Le tarif horaire applicable, delegue a la source unique. */
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

    /** LA ZONE DU PANIER, RÉSOLUE UNE BONNE FOIS — et écrite dessus. */
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

    /** La zone d'une POSITION : on nomme d'abord, on résout ensuite. */
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

    /** La couverture nationale, si la plateforme en déclare une. */
    public function nationalCoverage(): ?ServiceZone
    {
        return ServiceZone::query()
            ->where('coverage_type', 'national')
            ->where('status', 'active')
            ->where('is_bookable', true)
            ->orderBy('priority')
            ->first();
    }

    /** La zone qui dessert cette adresse. */
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
