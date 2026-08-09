<?php

namespace App\Services\Matching;

use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Presence\ProviderPresenceService;
use App\Support\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MatchingV2Service
{
    public function __construct(
        protected EmployeeAvailabilityService $availability,
        protected MatchingScoreEngine $engine,
    ) {}

    /**
     * Returns ranked candidates with full scoring breakdown.
     * Each item: ['employee' => User, 'breakdown' => MatchingScoreBreakdown, 'score' => float]
     */
    public function rankCandidates(Booking $booking, array $contextOverrides = []): Collection
    {
        if (! $booking->service_zone_id) {
            return collect();
        }

        $candidates = $this->eligibleCandidates($booking);
        if ($candidates->isEmpty()) {
            return collect();
        }

        $ranked = $candidates
            ->map(function (User $provider) use ($booking, $contextOverrides) {
                $breakdown = $this->engine->score($provider, $booking, $contextOverrides);

                return [
                    'employee' => $provider,
                    'breakdown' => $breakdown,
                    'score' => $breakdown->totalScore,
                ];
            })
            ->sortByDesc('score')
            ->values();

        $minScore = (float) Config::get('matching.thresholds.min_acceptable_score', 30);
        $fallback = (bool) Config::get('matching.thresholds.fallback_if_no_match', true);

        $eligible = $ranked->filter(fn ($r) => $r['score'] >= $minScore);

        if ($eligible->isEmpty() && $fallback) {
            return $ranked;
        }

        return $eligible->values();
    }

    public function bestFor(Booking $booking, array $contextOverrides = []): ?User
    {
        $ranked = $this->rankCandidates($booking, $contextOverrides);
        if ($ranked->isEmpty()) {
            return null;
        }

        $this->recordDecision($booking, $ranked);

        return $ranked->first()['employee'];
    }

    public function topN(Booking $booking, ?int $n = null, array $contextOverrides = []): Collection
    {
        $n ??= (int) Config::get('matching.top_n', 5);

        return $this->rankCandidates($booking, $contextOverrides)->take($n);
    }

    protected function eligibleCandidates(Booking $booking): Collection
    {
        /*
         * EN LIGNE SELON PRESENCE V2, pas selon le miroir binaire.
         *
         * `provider_profiles.is_online` reste vrai quand l'application est morte depuis vingt
         * minutes : c'est un drapeau qu'on pose, pas un signe de vie.
         */
        $enLigne = ($booking->booking_mode ?? null) === 'asap'
            ? app(ProviderPresenceService::class)->availableProviderIds()
            : [];

        $candidates = $this->availability
            ->sortedEligibleEmployeesForZone(
                (int) $booking->service_zone_id,
                $booking->provider_type_preference ?: 'any',
                $booking->assigned_provider_organization_id,
            )
            ->filter(function (User $employee) use ($booking, $enLigne) {
                if (($booking->booking_mode ?? null) === 'asap' && ! in_array((int) $employee->id, $enLigne, true)) {
                    return false;
                }

                return true;
            });

        return $this->applyTradeFilter($candidates, $booking);
    }

    protected function applyTradeFilter(Collection $candidates, Booking $booking): Collection
    {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $tradeId = $booking->resolveTradeId();

        /*
         * SANS METIER CONNU, ON NE REND PERSONNE — et le filtre n'a plus de repli.
         *
         * Il rendait la liste NON filtree quand elle se vidait, « pour ne pas bloquer le
         * dispatch ». Une mission non pourvue est un incident visible ; une mission pourvue par le
         * mauvais metier est un client perdu et un prestataire qui perd son deplacement.
         */
        if (! $tradeId) {
            Log::warning('MatchingV2: reservation sans metier resolvable, aucun candidat rendu.', [
                'booking_id' => $booking->id,
            ]);

            return collect();
        }

        $candidates->loadMissing('trades:id');

        return $candidates->filter(
            fn (User $employee) => $employee->trades->contains('id', $tradeId)
        );
    }

    protected function recordDecision(Booking $booking, Collection $ranked): void
    {
        try {
            $top = $ranked->first();
            $runnerUp = $ranked->get(1);

            $candidatesBreakdown = $ranked
                ->take(10)
                ->map(fn ($r) => $r['breakdown']->toArray())
                ->values()
                ->all();

            BookingMatchingDecision::create([
                'booking_id' => $booking->id,
                'selected_user_id' => $top['employee']->id,
                'candidates_count' => $ranked->count(),
                'selected_score' => $top['score'],
                'top_score' => $top['score'],
                'runner_up_score' => $runnerUp['score'] ?? null,
                'algorithm_version' => (string) Config::get('matching.version', 'v2'),
                'strategy' => $booking->booking_mode ?? 'scheduled',
                'weights_snapshot' => $this->engine->weights(),
                'candidates_breakdown' => $candidatesBreakdown,
                'selected_breakdown' => $top['breakdown']->toArray(),
            ]);

            ActivityLogger::log('matching.decision_recorded', $booking, [
                'selected_user_id' => $top['employee']->id,
                'top_score' => $top['score'],
                'candidates' => $ranked->count(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
