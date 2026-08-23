<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\GeolocationV2\DistanceCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Combien de prestataires, à quelle distance, et pour quand. */
class ProviderAvailabilityLookup
{
    public function __construct(
        protected DistanceCalculator $distance,
        protected AvailabilityService $availability,
    ) {}

    public function forTrade(Trade $trade, float $lat, float $lng, ?int $radiusM = null): AvailabilitySnapshot
    {
        $radiusM ??= (int) Config::get('order_engine.availability_radius_m', 8000);

        $providers = $this->providersOf($trade);
        $locatable = $providers->filter(fn ($p) => $p->current_lat !== null && $p->current_lng !== null);

        // Sans aucune position connue, on ne dit rien : un compte non vérifiable n'est pas un compte.
        if ($locatable->isEmpty()) {
            return new AvailabilitySnapshot(0, $radiusM, null, 0, $this->neighbouringTrades($trade, $lat, $lng, $radiusM));
        }

        $withinRadius = $this->within($locatable, $lat, $lng, $radiusM);

        if ($withinRadius->isEmpty()) {
            // Impasse : jamais d'écran mort.
            $wider = (int) Config::get('order_engine.availability_wider_radius_m', 25000);

            return new AvailabilitySnapshot(
                providerCount: 0,
                radiusM: $radiusM,
                earliestAt: null,
                providersLocatable: $locatable->count(),
                nearbyTrades: $this->neighbouringTrades($trade, $lat, $lng, $wider),
                widerRadiusM: $wider,
                widerRadiusCount: $this->within($locatable, $lat, $lng, $wider)->count(),
            );
        }

        return new AvailabilitySnapshot(
            providerCount: $withinRadius->count(),
            radiusM: $radiusM,
            earliestAt: $this->earliestSlot($withinRadius, $trade),
            providersLocatable: $locatable->count(),
        );
    }

    /**
     * Les prestataires du métier réellement dans le rayon, avec leur distance.
     *
     * @return Collection<int, array{id: int, distance_m: int}>
     */
    public function nearby(Trade $trade, float $lat, float $lng, ?int $radiusM = null): Collection
    {
        $radiusM ??= (int) Config::get('order_engine.availability_radius_m', 8000);

        $locatable = $this->providersOf($trade)
            ->filter(fn ($p) => $p->current_lat !== null && $p->current_lng !== null);

        return $this->within($locatable, $lat, $lng, $radiusM)
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'distance_m' => (int) round($this->distance->distanceMeters(
                    $lat, $lng, (float) $p->current_lat, (float) $p->current_lng,
                )),
            ])
            ->sortBy('distance_m')
            ->values();
    }

    /**
     * Prestataires actifs qui exercent ce métier.
     *
     * @return Collection<int, object>
     */
    protected function providersOf(Trade $trade): Collection
    {
        // LA POSITION VIENT DE PRESENCE V2, avec repli sur le profil.
        return DB::table('trade_user')
            ->join('users', 'users.id', '=', 'trade_user.user_id')
            ->join('provider_profiles', 'provider_profiles.user_id', '=', 'users.id')
            ->leftJoin('provider_presence', 'provider_presence.provider_user_id', '=', 'users.id')
            ->where('trade_user.trade_id', $trade->id)
            ->where('provider_profiles.status', 'active')
            ->whereIn('users.role', [User::ROLE_PROVIDER, User::ROLE_EMPLOYE])
            ->select([
                'users.id',
                DB::raw('COALESCE(provider_presence.current_lat, provider_profiles.current_lat) as current_lat'),
                DB::raw('COALESCE(provider_presence.current_lng, provider_profiles.current_lng) as current_lng'),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object>  $providers
     * @return Collection<int, object>
     */
    protected function within(Collection $providers, float $lat, float $lng, int $radiusM): Collection
    {
        // Pré-filtre par boîte englobante avant le calcul exact.
        $latDelta = $radiusM / 111_320;
        $lngDelta = $radiusM / max(1, 111_320 * cos(deg2rad($lat)));

        return $providers
            ->filter(fn ($p) => abs((float) $p->current_lat - $lat) <= $latDelta
                && abs((float) $p->current_lng - $lng) <= $lngDelta)
            ->filter(fn ($p) => $this->distance->distanceMeters(
                $lat, $lng, (float) $p->current_lat, (float) $p->current_lng,
            ) <= $radiusM)
            ->values();
    }

    /**
     * Le premier créneau réellement libre, parmi les prestataires les plus proches.
     *
     * @param  Collection<int, object>  $providers
     */
    protected function earliestSlot(Collection $providers, Trade $trade): ?Carbon
    {
        $sampleSize = (int) Config::get('order_engine.availability_sample_size', 5);
        $horizonDays = (int) Config::get('order_engine.availability_horizon_days', 7);
        $duration = max(30, (int) ($trade->estimated_duration_min ?? 60));

        $from = Carbon::now();
        $to = $from->copy()->addDays($horizonDays);
        $earliest = null;

        foreach ($providers->take($sampleSize) as $row) {
            $provider = User::find($row->id);

            if (! $provider) {
                continue;
            }

            try {
                foreach ($this->availability->getAvailableWindows($provider, $from, $to) as $window) {
                    $start = $this->windowStart($window);

                    if (! $start || ! $this->windowFits($window, $duration)) {
                        continue;
                    }

                    if ($earliest === null || $start->lt($earliest)) {
                        $earliest = $start;
                    }
                }
            } catch (\Throwable $e) {
                // Soft-fail volontaire : la disponibilité est un CONFORT d'affichage.
                Log::warning('[order_engine] agenda prestataire illisible', [
                    'provider_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $earliest;
    }

    /** Le format des fenêtres appartient au module Disponibilité : on le lit sans le présumer. */
    protected function windowStart(mixed $window): ?Carbon
    {
        $raw = is_array($window) ? ($window['start'] ?? $window['starts_at'] ?? null) : null;

        if ($raw === null) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Une fenêtre trop courte pour la prestation n'en est pas une. */
    protected function windowFits(mixed $window, int $durationMin): bool
    {
        if (! is_array($window)) {
            return false;
        }

        $end = $window['end'] ?? $window['ends_at'] ?? null;
        $start = $this->windowStart($window);

        if ($start === null || $end === null) {
            // Sans fin déclarée, on ne peut rien affirmer sur la durée : on accepte, la page
            // n'annonce qu'une possibilité, pas un engagement.
            return true;
        }

        try {
            return $start->diffInMinutes(Carbon::parse($end)) >= $durationMin;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Les métiers voisins qui, eux, sont couverts.
     *
     * @return list<array{trade_id: int, name: string, slug: string, provider_count: int}>
     */
    protected function neighbouringTrades(Trade $trade, float $lat, float $lng, int $radiusM): array
    {
        if (! $trade->sector_id) {
            return [];
        }

        return Trade::query()
            ->where('sector_id', $trade->sector_id)
            ->where('id', '!=', $trade->id)
            ->where('is_active', true)
            ->get()
            ->map(function (Trade $neighbour) use ($lat, $lng, $radiusM) {
                $locatable = $this->providersOf($neighbour)
                    ->filter(fn ($p) => $p->current_lat !== null && $p->current_lng !== null);

                return [
                    'trade_id' => $neighbour->id,
                    'name' => $neighbour->name,
                    'slug' => $neighbour->slug,
                    'provider_count' => $this->within($locatable, $lat, $lng, $radiusM)->count(),
                ];
            })
            ->filter(fn (array $row) => $row['provider_count'] > 0)
            ->sortByDesc('provider_count')
            ->take(3)
            ->values()
            ->all();
    }
}
