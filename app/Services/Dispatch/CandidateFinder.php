<?php

namespace App\Services\Dispatch;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Trade;
use App\Models\User;
use App\Services\FaceCheck\FaceCheckDispatchFilter;
use App\Services\Matching\MatchingScoreEngine;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Services\Safety\UserSafetyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** QUI PEUT RECEVOIR CETTE MISSION — une seule réponse, une seule requête. */
class CandidateFinder
{
    public function __construct(
        protected MatchingScoreEngine $scoreEngine,
        protected ZonePricingResolver $catalogue,
    ) {}

    /**
     * Les candidats d'une INTERVENTION IMMÉDIATE, du plus proche au plus lointain.
     *
     * @param  list<int>  $excludeUserIds
     * @return Collection<int, DispatchCandidate>
     */
    public function immediate(Booking $booking, int $radiusM, array $excludeUserIds = []): Collection
    {
        $lat = $this->toFloat($booking->getAttribute('destination_lat'));
        $lng = $this->toFloat($booking->getAttribute('destination_lng'));

        // Sans destination géocodée, « le plus proche » ne veut rien dire. On ne devine pas : le
        // planifié sait travailler sur les zones déclarées, l'immédiat non.
        if ($lat === null || $lng === null) {
            return collect();
        }

        $query = $this->baseQuery($booking, $excludeUserIds);

        if ($query === null) {
            return collect();
        }

        $freshness = (int) Config::get('dispatch.position_freshness_minutes', 5);

        $latDelta = $radiusM / 111_320;
        $lngDelta = $radiusM / max(1.0, 111_320 * cos(deg2rad($lat)));

        $query
            ->join('provider_presence as dispatch_presence', 'dispatch_presence.provider_user_id', '=', 'users.id')
            ->where('dispatch_presence.status', 'online')
            ->where('dispatch_presence.heartbeat_at', '>=', now()->subMinutes($freshness))
            ->whereNotNull('dispatch_presence.current_lat')
            ->whereNotNull('dispatch_presence.current_lng')
            ->whereBetween('dispatch_presence.current_lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('dispatch_presence.current_lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->addSelect([
                'dispatch_presence.current_lat as dispatch_lat',
                'dispatch_presence.current_lng as dispatch_lng',
            ]);

        return $query->get()
            ->map(fn (User $user) => new DispatchCandidate(
                $user,
                (int) round($this->haversineMeters(
                    $lat,
                    $lng,
                    (float) $user->getAttribute('dispatch_lat'),
                    (float) $user->getAttribute('dispatch_lng'),
                )),
                $this->scoreOf($user, $booking),
            ))
            ->filter(fn (DispatchCandidate $c) => $c->distanceM !== null && $c->distanceM <= $radiusM)
            // Proximité d'abord, score en départage : deux prestataires à la même distance sont
            // départagés par leur qualité, jamais l'inverse.
            ->sortBy(fn (DispatchCandidate $c) => [$c->distanceM, -$c->score])
            ->values();
    }

    /**
     * Les candidats d'un RENDEZ-VOUS PLANIFIÉ, du meilleur au moins bon.
     *
     * @param  list<int>  $excludeUserIds
     * @return Collection<int, DispatchCandidate>
     */
    public function scheduled(Booking $booking, array $excludeUserIds = []): Collection
    {
        $query = $this->baseQuery($booking, $excludeUserIds);

        if ($query === null) {
            return collect();
        }

        $zoneId = (int) ($booking->service_zone_id ?? 0);

        if ($zoneId > 0) {
            $now = now();

            $query->where(function (Builder $scope) use ($zoneId, $now) {
                $scope
                    ->where('users.primary_service_zone_id', $zoneId)
                    ->orWhereExists(function ($sub) use ($zoneId, $now) {
                        $sub->select(DB::raw(1))
                            ->from('employee_zone_assignments')
                            ->whereColumn('employee_zone_assignments.user_id', 'users.id')
                            ->where('employee_zone_assignments.service_zone_id', $zoneId)
                            ->where('employee_zone_assignments.is_active', true)
                            ->where(function ($w) use ($now) {
                                $w->whereNull('employee_zone_assignments.starts_at')
                                    ->orWhere('employee_zone_assignments.starts_at', '<=', $now);
                            })
                            ->where(function ($w) use ($now) {
                                $w->whereNull('employee_zone_assignments.ends_at')
                                    ->orWhere('employee_zone_assignments.ends_at', '>=', $now);
                            });
                    });
            });
        }

        $lat = $this->toFloat($booking->getAttribute('destination_lat'));
        $lng = $this->toFloat($booking->getAttribute('destination_lng'));

        return $query->get()
            ->map(function (User $user) use ($booking, $lat, $lng) {
                $profile = $user->providerProfile;
                $profilLat = $profile ? $this->toFloat($profile->getAttribute('current_lat')) : null;
                $profilLng = $profile ? $this->toFloat($profile->getAttribute('current_lng')) : null;

                $distance = ($lat !== null && $lng !== null && $profilLat !== null && $profilLng !== null)
                    ? (int) round($this->haversineMeters($lat, $lng, $profilLat, $profilLng))
                    : null;

                return new DispatchCandidate($user, $distance, $this->scoreOf($user, $booking));
            })
            ->sortByDesc(fn (DispatchCandidate $c) => $c->score)
            ->values();
    }

    /** Un décimal de base rendu utilisable, ou `null` — jamais un `0.0` qui mentirait sur la carte. */
    protected function toFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /** Le métier exigé par cette réservation. */
    public function tradeIdFor(Booking $booking): ?int
    {
        return $booking->resolveTradeId();
    }

    /**
     * Le socle commun : métier, éligibilité, société, exclusions.
     *
     * @param  list<int>  $excludeUserIds
     * @return Builder<User>|null
     */
    protected function baseQuery(Booking $booking, array $excludeUserIds = []): ?Builder
    {
        $tradeId = $this->tradeIdFor($booking);

        if (! $tradeId) {
            return null;
        }

        $blocked = $booking->client_id
            ? app(UserSafetyService::class)->blockedUserIdsFor((int) $booking->client_id)
            : [];

        $excluded = array_values(array_unique(array_map('intval', array_merge($excludeUserIds, $blocked))));

        // LE COUPLE (MÉTIER, ZONE) DOIT ÊTRE OUVERT AU CATALOGUE.
        if ($booking->service_zone_id
            && ! $this->catalogue->isOpen($tradeId, (int) $booking->service_zone_id)) {
            return null;
        }

        $query = User::query()
            ->select('users.*')
            ->join('provider_profiles as dispatch_profile', 'dispatch_profile.user_id', '=', 'users.id')
            // LA JOINTURE QUI TIENT L'INVARIANT.
            ->join('trade_user as dispatch_trade', function ($join) use ($tradeId) {
                $join->on('dispatch_trade.user_id', '=', 'users.id')
                    ->where('dispatch_trade.trade_id', '=', $tradeId);
            })
            ->where('users.is_active', true)
            ->where('dispatch_profile.status', 'active')
            // KYC : la vérification d'identité est un blocage strict, à l'offre comme à
            // l'acceptation. Un prestataire non vérifié ne doit pas même voir la modale.
            ->where('dispatch_profile.verification_status', 'verified')
            ->whereIn('dispatch_profile.provider_type', $this->providerTypeValues($booking->provider_type_preference ?: 'any'))
            ->with('providerProfile');

        // Le client a choisi une société : seuls ses salariés concourent. Sans ce filtre, la
        // mission partirait chez un indépendant que le client n'a pas choisi.
        if ($booking->assigned_provider_organization_id) {
            $query->where('dispatch_profile.organization_account_id', (int) $booking->assigned_provider_organization_id);
        }

        if ($excluded !== []) {
            $query->whereNotIn('users.id', $excluded);
        }

        // ON NE CONDUIT PAS SANS PERMIS — et la règle vaut MÉTIER PAR MÉTIER.
        $trade = Trade::with('questions')->find($tradeId);

        if ($trade) {
            app(ConduiteRequirements::class)->appliquerAuxCandidats($query, $trade);
        }

        // LE VISAGE, POUR LES MÉTIERS QUI L'EXIGENT.
        app(FaceCheckDispatchFilter::class)->appliquerAuxCandidats($query, $booking);

        // UNE SEULE OFFRE À LA FOIS.
        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('mission_assignments')
                ->whereColumn('mission_assignments.user_id', 'users.id')
                ->where('mission_assignments.assignment_status', 'assigned')
                ->where(function ($w) {
                    $w->whereNull('mission_assignments.expires_at')
                        ->orWhere('mission_assignments.expires_at', '>', now());
                });
        });

        return $query;
    }

    /** Le score du moteur à neuf dimensions — réutilisé, jamais réécrit. */
    protected function scoreOf(User $user, Booking $booking): float
    {
        try {
            return (float) $this->scoreEngine->score($user, $booking)->totalScore;
        } catch (\Throwable) {
            // Le score est un DÉPARTAGE. Son indisponibilité ne doit pas vider une liste de
            // candidats par ailleurs éligibles — elle doit juste les laisser à égalité.
            return 0.0;
        }
    }

    /** @return list<string> */
    protected function providerTypeValues(string $providerType): array
    {
        return match ($providerType) {
            // La liste vient de l'énumération : elle seule sait quelles valeurs désignent
            // le même camp, et la recopier ici l'aurait laissée diverger.
            'independent' => ProviderType::valeursIndependantes(),
            'company' => ProviderType::valeursDeSociete(),
            default => ProviderType::toutesLesValeurs(),
        };
    }

    /** Haversine, en mètres. Écrit ici pour rester indépendant d'un module géo optionnel. */
    protected function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6_371_000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
