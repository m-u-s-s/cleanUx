<?php

namespace App\Services\Booking;

use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\ZoneServiceRule;

class BookingEstimatorService
{
    protected function resolveServiceIdentifier(array $context): ?string
    {
        $identifier = $context['service_identifier'] ?? null;
        return filled($identifier) ? (string) $identifier : null;
    }

    public function estimateDuration(?ServiceCatalog $catalog, array $context): int
    {
        $serviceIdentifier = $this->resolveServiceIdentifier($context);
        $baseMinutes = $catalog?->default_duration_minutes ?? match ($serviceIdentifier) {
            'nettoyage_standard' => 120,
            'nettoyage_profond' => 180,
            'fin_de_chantier' => 240,
            'fin_de_bail' => 240,
            'bureaux' => 150,
            default => 120,
        };

        $surfaceMinutes = match ($context['surface'] ?? null) {
            'moins_50' => 0,
            '50_100' => 30,
            '100_150' => 60,
            '150_250' => 90,
            'plus_250' => 120,
            default => 0,
        };

        $optionsMinutes = count((array) ($context['options_prestation'] ?? [])) * 20;
        $zonesMinutes = count((array) ($context['zones_specifiques'] ?? [])) * 10;
        $animauxMinutes = ! empty($context['presence_animaux']) ? 10 : 0;

        return $baseMinutes + $surfaceMinutes + $optionsMinutes + $zonesMinutes + $animauxMinutes;
    }

    public function estimatePrice(?ServiceCatalog $catalog, ?ServiceZone $zone, ?ZoneServiceRule $rule, array $context): float
    {
        $serviceIdentifier = $this->resolveServiceIdentifier($context);

        $basePrice = (float) ($catalog?->base_price ?? match ($serviceIdentifier) {
            'nettoyage_standard' => 79,
            'nettoyage_profond' => 129,
            'fin_de_chantier' => 189,
            'fin_de_bail' => 179,
            'bureaux' => 149,
            default => 79,
        });

        if ($rule?->base_price_override !== null) {
            $basePrice = (float) $rule->base_price_override;
        }

        if ($rule?->price_multiplier !== null) {
            $basePrice *= (float) $rule->price_multiplier;
        }

        $countryMultiplier = (float) ($context['country_price_multiplier'] ?? 1.0);
        if ($countryMultiplier > 0 && abs($countryMultiplier - 1.0) > 0.0001) {
            $basePrice *= $countryMultiplier;
        }

        $surfacePrice = match ($context['surface'] ?? null) {
            'moins_50' => 0,
            '50_100' => 20,
            '100_150' => 40,
            '150_250' => 70,
            'plus_250' => 100,
            default => 0,
        };

        $optionsPrice = count((array) ($context['options_prestation'] ?? [])) * 15;
        $zonesPrice = count((array) ($context['zones_specifiques'] ?? [])) * 8;
        $premiumPrice = ! empty($context['is_premium']) ? 10 : 0;
        $travelSurcharge = (float) ($zone?->travel_surcharge ?? 0);

        $subtotal = $basePrice + $surfacePrice + $optionsPrice + $zonesPrice + $premiumPrice + $travelSurcharge;
        $subtotal *= match ($context['frequence'] ?? null) {
            'hebdomadaire' => 0.92,
            'bihebdomadaire' => 0.95,
            'mensuel' => 0.97,
            default => 1,
        };

        return round($subtotal, 2);
    }
}
