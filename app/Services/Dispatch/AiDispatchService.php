<?php

namespace App\Services\Dispatch;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiDispatchService
{
    public function __construct(
        protected EmployeeAvailabilityService $availability,
    ) {}

    public function bestEmployeeFor(Booking $rdv): ?User
    {
        if ((bool) config('matching.enabled', true)) {
            try {
                $v2 = app(\App\Services\Matching\MatchingV2Service::class);
                $candidate = $v2->bestFor($rdv);
                if ($candidate) {
                    if ((bool) config('matching.shadow_mode', false)) {
                        $this->logShadowCompare($rdv, $candidate);
                    }
                    return $candidate;
                }
            } catch (\Throwable $e) {
                Log::warning('AiDispatch: MatchingV2 a échoué, fallback sur v1.', [
                    'booking_id' => $rdv->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->rankEmployees($rdv)->first()['employee'] ?? null;
    }

    protected function logShadowCompare(Booking $rdv, User $v2Choice): void
    {
        try {
            $v1Choice = $this->rankEmployees($rdv)->first()['employee'] ?? null;
            Log::info('matching.shadow_compare', [
                'booking_id' => $rdv->id,
                'v1_choice' => $v1Choice?->id,
                'v2_choice' => $v2Choice->id,
                'matches' => $v1Choice?->id === $v2Choice->id,
            ]);
        } catch (\Throwable $e) {
            // shadow logging is best-effort
        }
    }

    public function rankEmployees(Booking $rdv): Collection
    {
        if (! $rdv->service_zone_id || ! $rdv->date || ! $rdv->heure) {
            return collect();
        }

        $duration = (int) ($rdv->duree_estimee ?: $rdv->duree ?: 90);

        $candidates = $this->availability
            ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id)
            ->filter(function (User $employee) use ($rdv) {

                if ($rdv->booking_mode === 'asap') {
                    $profile = $employee->providerProfile;
                    if (! $profile || ! $profile->is_online) {
                        return false;
                    }
                }

                return true;
            });

        $candidates = $this->applyTradeFilter($candidates, $rdv);

        return $candidates
            ->map(fn(User $employee) => [
                'employee' => $employee,
                'score' => $this->score($employee, $rdv),
                'details' => $this->scoreDetails($employee, $rdv),
            ])
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Exclut les prestataires non habilités au métier requis par le booking.
     *
     * Le métier est résolu via le ServiceCatalog du booking. Si aucun trade
     * n'est rattaché au service, on ne filtre pas (back-compat).
     *
     * Fallback : si le filtre vide complètement la liste alors que des
     * candidats existaient, on retourne la liste originale + log warning.
     * Évite de bloquer le dispatch en phase de transition où les employés
     * n'ont pas encore tous été tagués trade.
     */
    protected function applyTradeFilter(Collection $candidates, Booking $rdv): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $tradeId = $rdv->serviceCatalog?->trade_id;
        if (! $tradeId) {
            return $candidates;
        }

        $candidates->loadMissing('trades:id');

        $filtered = $candidates->filter(
            fn (User $employee) => $employee->trades->contains('id', $tradeId)
        );

        if ($filtered->isEmpty()) {
            Log::warning('AiDispatch: aucun prestataire tagué pour le métier requis, fallback ouvert.', [
                'booking_id'         => $rdv->id,
                'required_trade_id'  => $tradeId,
                'open_candidates'    => $candidates->count(),
            ]);
            return $candidates;
        }

        return $filtered;
    }

    public function score(User $employee, Booking $rdv): int
    {
        return array_sum($this->scoreDetails($employee, $rdv));
    }

    public function scoreDetails(User $employee, Booking $rdv): array
    {
        return [
            'zone' => $this->zoneScore($employee, $rdv),
            'quality' => $this->qualityScore($employee),
            'workload' => $this->workloadScore($employee, $rdv),
            'favorite' => $this->favoriteScore($employee, $rdv),
            'premium' => $this->premiumScore($rdv),
            'urgency' => $this->urgencyScore($rdv),
            'reliability' => $this->reliabilityScore($employee),
        ];
    }

    protected function zoneScore(User $employee, Booking $rdv): int
    {
        if ((int) $employee->primary_service_zone_id === (int) $rdv->service_zone_id) {
            return 300;
        }

        $assignment = $employee->zoneAssignments
            ->firstWhere('service_zone_id', $rdv->service_zone_id);

        return match ($assignment?->assignment_type) {
            'primary' => 250,
            'secondary' => 150,
            'backup' => 80,
            default => 0,
        };
    }

    protected function qualityScore(User $employee): int
    {
        // Bug fix — la colonne `quality_score` n'existe pas sur la table
        // missions (elle a un JSON `quality_snapshot` à la place). MySQL
        // strict refuse une avg() sur colonne inexistante. SQLite tolère
        // silencieusement, ce qui masquait le bug en CI.
        //
        // Fallback : si la colonne n'existe pas, on tente une moyenne via
        // MissionQualityReview.score (donnée réelle), sinon on rend la
        // valeur par défaut.
        if (! Schema::hasColumn('missions', 'quality_score')) {
            return $this->qualityScoreFromReviews($employee);
        }

        $avg = (float) $employee->leadMissions()
            ->whereNotNull('quality_score')
            ->avg('quality_score');

        return $avg > 0 ? (int) round($avg * 2) : 120;
    }

    /**
     * Calcule la moyenne de quality via les MissionQualityReview liées aux
     * missions menées par l'employé. Renvoie le score par défaut si la table
     * ou la colonne n'existe pas.
     */
    protected function qualityScoreFromReviews(User $employee): int
    {
        if (! Schema::hasTable('mission_quality_reviews')
            || ! Schema::hasColumn('mission_quality_reviews', 'score')) {
            return 120;
        }

        $missionIds = $employee->leadMissions()->pluck('id');
        if ($missionIds->isEmpty()) {
            return 120;
        }

        $avg = (float) DB::table('mission_quality_reviews')
            ->whereIn('mission_id', $missionIds)
            ->whereNotNull('score')
            ->avg('score');

        return $avg > 0 ? (int) round($avg * 2) : 120;
    }

    protected function workloadScore(User $employee, Booking $rdv): int
    {
        $count = $employee->rendezVousEmploye()
            ->whereDate('date', $rdv->date)
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->count();

        return match (true) {
            $count === 0 => 220,
            $count === 1 => 140,
            $count === 2 => 50,
            default => -200,
        };
    }

    protected function favoriteScore(User $employee, Booking $rdv): int
    {
        $clientId = (int) ($rdv->client_id ?? 0);
        if ($clientId <= 0) {
            return 0;
        }

        if (! Schema::hasTable('client_provider_preferences')) {
            return 0;
        }

        $query = DB::table('client_provider_preferences')
            ->where('provider_user_id', $employee->id);

        if (Schema::hasColumn('client_provider_preferences', 'client_user_id')) {
            $query->where('client_user_id', $clientId);
        } elseif (Schema::hasColumn('client_provider_preferences', 'client_id')) {
            $query->where('client_id', $clientId);
        } else {
            return 0;
        }

        if (Schema::hasColumn('client_provider_preferences', 'is_favorite')) {
            $query->where('is_favorite', true);
        }

        return $query->exists() ? 80 : 0;
    }

    protected function premiumScore(Booking $rdv): int
    {
        return $rdv->client && method_exists($rdv->client, 'isPremium') && $rdv->client->isPremium()
            ? 80
            : 0;
    }

    protected function urgencyScore(Booking $rdv): int
    {
        return $rdv->priorite === 'urgente' ? 120 : 0;
    }

    protected function reliabilityScore(User $employee): int
    {
        $missions = $employee->leadMissions()->count();

        if ($missions === 0) {
            return 100;
        }

        $completed = $employee->leadMissions()
            ->where('status', 'completed')
            ->count();

        return (int) round(($completed / max(1, $missions)) * 150);
    }
}
