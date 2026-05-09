<?php

namespace App\Services\Pricing;

use App\Models\Booking;
use App\Models\PricingZoneState;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use Carbon\Carbon;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 14 — Moteur de surge pricing avancé (Uber-style).
 *
 * Remplace le DynamicPricingService 32 lignes par un vrai engine multi-critères :
 *
 *   multiplier = demand_factor × supply_factor × temporal_factor × asap_extra
 *
 * Avec :
 *   - demand_factor : nombre de bookings ouverts dans la zone (60 dernières minutes)
 *   - supply_factor : inverse du nombre de prestataires online dans la zone
 *   - temporal_factor : pics horaires + weekend
 *   - asap_extra : ×1.25 si mode ASAP
 *
 * Tous capped à `surge.max_multiplier` (défaut 3.0).
 *
 * Stratégie cache :
 *   - Le multiplier d'une zone est stocké dans pricing_zones_state
 *   - Recalculé par RecomputeSurgeJob (toutes les 60s par défaut)
 *   - Si pas calculé depuis surge.state_ttl_seconds → on revient à 1.0
 *     (decay naturel, évite les surges figés sur des données obsolètes)
 *
 * Pour une mission individuelle, on multiplie le prix de base par le multiplier
 * de la zone, puis on applique les extras spécifiques (ASAP, etc.).
 */
class SurgePricingEngine
{
    /**
     * Calcule le prix final pour un booking.
     *
     * Renvoie un array détaillé pour traçabilité (UI client + audit) :
     *   - base_price
     *   - final_price
     *   - multiplier
     *   - factors: { demand, supply, temporal, asap }
     *   - is_visible (si on doit afficher un warning client)
     *   - source: 'live'|'cached'|'default'
     */
    public function calculate(float $basePrice, ?ServiceZone $zone = null, array $context = []): array
    {
        $multiplier = 1.0;
        $factors = [
            'demand'   => 1.0,
            'supply'   => 1.0,
            'temporal' => 1.0,
            'asap'     => 1.0,
        ];

        $source = 'default';

        // 1. Multiplier de zone (depuis pricing_zones_state si dispo)
        if ($zone) {
            $state = PricingZoneState::where('service_zone_id', $zone->id)->first();
            if ($state && $state->isActive()) {
                $factors['demand'] = (float) $state->demand_factor;
                $factors['supply'] = (float) $state->supply_factor;
                $factors['temporal'] = (float) $state->temporal_factor;
                $multiplier = (float) $state->multiplier;
                $source = 'cached';
            } else {
                // Calcul live si pas de state ou expiré
                $live = $this->computeForZone($zone);
                $factors['demand']   = $live['demand_factor'];
                $factors['supply']   = $live['supply_factor'];
                $factors['temporal'] = $live['temporal_factor'];
                $multiplier = $live['multiplier'];
                $source = 'live';
            }
        } else {
            // Pas de zone → seul le facteur temporel s'applique
            $factors['temporal'] = $this->temporalFactor();
            $multiplier = $factors['temporal'];
            $source = 'live';
        }

        // 2. Extra ASAP
        if (($context['booking_mode'] ?? 'scheduled') === 'asap') {
            $factors['asap'] = (float) config('surge.asap_extra_multiplier', 1.25);
            $multiplier *= $factors['asap'];
        }

        // 3. Cap absolu
        $maxMultiplier = (float) config('surge.max_multiplier', 3.0);
        $multiplier = min($multiplier, $maxMultiplier);

        $finalPrice = round($basePrice * $multiplier, 2);

        return [
            'base_price'     => round($basePrice, 2),
            'final_price'    => $finalPrice,
            'multiplier'     => round($multiplier, 2),
            'factors'        => array_map(fn($f) => round($f, 2), $factors),
            'is_visible'     => $multiplier >= (float) config('surge.visible_threshold', 1.20),
            'source'         => $source,
            'capped'         => $multiplier >= $maxMultiplier,
        ];
    }

    /**
     * Recalcule le surge pour une zone et persiste dans pricing_zones_state.
     *
     * Appelé par RecomputeSurgeJob (toutes les 60s par défaut).
     */
    public function recomputeForZone(ServiceZone $zone): PricingZoneState
    {
        return DB::transaction(function () use ($zone) {
            $live = $this->computeForZone($zone);
            $ttl = (int) config('surge.state_ttl_seconds', 600);

            return PricingZoneState::updateOrCreate(
                ['service_zone_id' => $zone->id],
                [
                    'multiplier'             => $live['multiplier'],
                    'demand_factor'          => $live['demand_factor'],
                    'supply_factor'          => $live['supply_factor'],
                    'temporal_factor'        => $live['temporal_factor'],
                    'open_bookings_count'    => $live['open_bookings_count'],
                    'online_providers_count' => $live['online_providers_count'],
                    'expires_at'             => now()->addSeconds($ttl),
                    'metadata'               => [
                        'computed_at'  => now()->toIso8601String(),
                        'is_weekend'   => now()->isWeekend(),
                    ],
                ]
            );
        });
    }

    /**
     * Calcul live des facteurs pour une zone (sans cache).
     */
    public function computeForZone(ServiceZone $zone): array
    {
        $demand = $this->demandFactor($zone);
        $supply = $this->supplyFactor($zone);
        $temporal = $this->temporalFactor();

        $multiplier = $demand['factor'] * $supply['factor'] * $temporal;
        $maxMultiplier = (float) config('surge.max_multiplier', 3.0);
        $multiplier = min($multiplier, $maxMultiplier);

        return [
            'multiplier'             => round($multiplier, 2),
            'demand_factor'          => round($demand['factor'], 2),
            'supply_factor'          => round($supply['factor'], 2),
            'temporal_factor'        => round($temporal, 2),
            'open_bookings_count'    => $demand['count'],
            'online_providers_count' => $supply['count'],
        ];
    }

    /**
     * Calcule le facteur "demand" : nombre de bookings ouverts dans la zone
     * sur les X dernières minutes.
     */
    protected function demandFactor(ServiceZone $zone): array
    {
        $config = config('surge.demand');
        $lookback = (int) ($config['lookback_minutes'] ?? 60);

        $count = Booking::query()
            ->where('service_zone_id', $zone->id)
            ->where('created_at', '>=', now()->subMinutes($lookback))
            ->whereNotIn('status', ['annule', 'cancelled', 'refuse'])
            ->count();

        $threshold = (int) ($config['threshold'] ?? 5);
        $weight = (float) ($config['weight'] ?? 0.05);
        $cap = (float) ($config['cap'] ?? 1.5);

        $factor = 1.0;
        if ($count > $threshold) {
            $factor = 1.0 + ($count - $threshold) * $weight;
            $factor = min($factor, $cap);
        }

        return ['factor' => $factor, 'count' => $count];
    }

    /**
     * Calcule le facteur "supply" : inverse du nombre de prestataires online
     * dans (ou proche de) la zone.
     */
    protected function supplyFactor(ServiceZone $zone): array
    {
        $config = config('surge.supply');

        // Pour MVP : compte les providers online ayant cette zone comme primaire
        // ou backup. Pour version géo plus précise, calculer via haversine.
        $count = ProviderProfile::query()
            ->where('is_online', true)
            ->whereHas('user.zoneAssignments', function ($q) use ($zone) {
                $q->where('service_zone_id', $zone->id);
            })
            ->count();

        // Fallback si la relation zoneAssignments n'existe pas (selon ton schema)
        if ($count === 0) {
            $count = ProviderProfile::query()
                ->where('is_online', true)
                ->count();
        }

        $threshold = (int) ($config['threshold'] ?? 3);
        $weight = (float) ($config['weight'] ?? 0.15);
        $cap = (float) ($config['cap'] ?? 1.6);

        $factor = 1.0;
        if ($count < $threshold) {
            $factor = 1.0 + ($threshold - $count) * $weight;
            $factor = min($factor, $cap);
        }

        return ['factor' => $factor, 'count' => $count];
    }

    /**
     * Facteur temporel : pics horaires + weekend.
     */
    protected function temporalFactor(?Carbon $now = null): float
    {
        $now = $now ?? IlluminateCarbon::now(config('app.timezone', 'Europe/Brussels'));
        $hour = (int) $now->format('H');
        $factor = 1.0;

        // Pics horaires
        $peaks = config('surge.temporal.peaks', []);
        foreach ($peaks as $range => $peakMultiplier) {
            [$start, $end] = explode('-', $range);
            $start = (int) $start;
            $end = (int) $end;

            $inRange = $start <= $end
                ? ($hour >= $start && $hour < $end)
                : ($hour >= $start || $hour < $end); // wrap-around 22-02

            if ($inRange) {
                $factor = max($factor, (float) $peakMultiplier);
            }
        }

        // Bonus weekend
        if ($now->isWeekend()) {
            $factor += (float) config('surge.temporal.weekend_extra', 0.10);
        }

        return $factor;
    }

    // NB : une méthode boot() existait ici précédemment et tentait de faire
    // `$this->app->bind(...)`. C'était du code mort : cette classe n'a
    // jamais été un ServiceProvider, `$this->app` n'existe pas, et la méthode
    // n'était jamais appelée. La logique de délégation a été déplacée
    // directement dans DynamicPricingService::calculate() (forward).
}
