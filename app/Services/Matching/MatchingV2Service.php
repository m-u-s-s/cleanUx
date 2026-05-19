<?php

namespace App\Services\Matching;

use App\Models\Booking;
use App\Models\BookingMatchingDecision;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
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
        $candidates = $this->availability
            ->sortedEligibleEmployeesForZone((int) $booking->service_zone_id)
            ->filter(function (User $employee) use ($booking) {
                if ($booking->booking_mode === 'asap') {
                    $profile = $employee->providerProfile;
                    if (! $profile || ! $profile->is_online) {
                        return false;
                    }
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

        $tradeId = $booking->serviceCatalog?->trade_id;
        if (! $tradeId) {
            return $candidates;
        }

        $candidates->loadMissing('trades:id');

        $filtered = $candidates->filter(
            fn (User $employee) => $employee->trades->contains('id', $tradeId)
        );

        if ($filtered->isEmpty()) {
            Log::warning('MatchingV2: aucun prestataire tagué pour le métier requis, fallback ouvert.', [
                'booking_id' => $booking->id,
                'required_trade_id' => $tradeId,
                'open_candidates' => $candidates->count(),
            ]);
            return $candidates;
        }

        return $filtered;
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
