<?php

namespace App\Services\Matching;

use App\Models\Booking;
use App\Models\ProviderPerformanceMetric;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MatchingScoreEngine
{
    public function __construct(protected ProviderPerformanceCalculator $perfCalculator)
    {
    }

    /**
     * Score a provider for a given booking. Returns 0–100 with full breakdown.
     */
    public function score(User $provider, Booking $booking, array $contextOverrides = []): MatchingScoreBreakdown
    {
        $weights = $this->weights();
        $context = $this->buildContext($provider, $booking, $contextOverrides);

        $rawScores = [
            'rating' => $this->ratingScore($provider, $context),
            'acceptance_rate' => $this->acceptanceScore($context),
            'completion_rate' => $this->completionScore($context),
            'response_time' => $this->responseTimeScore($context),
            'zone_proximity' => $this->zoneScore($provider, $booking),
            'workload' => $this->workloadScore($provider, $booking),
            'client_affinity' => $this->clientAffinityScore($provider, $booking, $context),
            'trade_specialty' => $this->tradeScore($provider, $booking),
            'recency_balance' => $this->recencyBalanceScore($context),
        ];

        $components = [];
        $total = 0.0;
        foreach ($weights as $key => $weight) {
            $raw = max(0.0, min(100.0, (float) ($rawScores[$key] ?? 0)));
            $weighted = round(($raw * $weight) / 100, 2);
            $components[$key] = [
                'raw' => round($raw, 2),
                'weight' => (int) $weight,
                'weighted' => $weighted,
            ];
            $total += $weighted;
        }

        return new MatchingScoreBreakdown(
            userId: (int) $provider->id,
            totalScore: round($total, 2),
            components: $components,
            context: [
                'rating_avg' => $context['rating_avg'] ?? null,
                'acceptance_rate' => $context['acceptance_rate'] ?? null,
                'completion_rate' => $context['completion_rate'] ?? null,
                'avg_response_seconds' => $context['avg_response_seconds'] ?? null,
                'recent_missions_24h' => $context['recent_missions_24h'] ?? 0,
            ],
        );
    }

    public function weights(): array
    {
        return Config::get('matching.weights', []);
    }

    protected function buildContext(User $provider, Booking $booking, array $overrides = []): array
    {
        $profile = $provider->providerProfile;
        $metric = $this->perfCalculator->latestFor((int) $provider->id);

        $context = [
            'rating_avg' => $profile?->rating_avg !== null ? (float) $profile->rating_avg : null,
            'rating_count' => (int) ($profile->rating_count ?? 0),
            'acceptance_rate' => $metric?->acceptance_rate !== null ? (float) $metric->acceptance_rate : null,
            'completion_rate' => $metric?->completion_rate !== null ? (float) $metric->completion_rate : null,
            'avg_response_seconds' => $metric?->avg_response_seconds,
            'recent_missions_24h' => $this->recentMissionsCount(
                (int) $provider->id,
                (int) Config::get('matching.diversification.recent_window_hours', 24)
            ),
        ];

        return array_merge($context, $overrides);
    }

    protected function ratingScore(User $provider, array $context): float
    {
        $avg = $context['rating_avg'] ?? null;
        $count = $context['rating_count'] ?? 0;

        if ($avg === null || $count === 0) {
            return 60.0;
        }

        $base = (float) $avg / 5 * 100;

        $confidence = min(1.0, $count / 10);

        return $base * $confidence + (1 - $confidence) * 60;
    }

    protected function acceptanceScore(array $context): float
    {
        $rate = $context['acceptance_rate'] ?? null;
        if ($rate === null) {
            return 70.0;
        }
        return min(100.0, ((float) $rate) * 100);
    }

    protected function completionScore(array $context): float
    {
        $rate = $context['completion_rate'] ?? null;
        if ($rate === null) {
            return 75.0;
        }
        return min(100.0, ((float) $rate) * 100);
    }

    protected function responseTimeScore(array $context): float
    {
        $seconds = $context['avg_response_seconds'] ?? null;
        if ($seconds === null || $seconds <= 0) {
            return 60.0;
        }

        $excellent = (int) Config::get('matching.response_time.excellent_seconds', 60);
        $poor = (int) Config::get('matching.response_time.poor_seconds', 600);

        if ($seconds <= $excellent) {
            return 100.0;
        }
        if ($seconds >= $poor) {
            return 0.0;
        }

        $range = $poor - $excellent;
        $progress = ($seconds - $excellent) / $range;
        return round(100 * (1 - $progress), 2);
    }

    protected function zoneScore(User $provider, Booking $booking): float
    {
        if (! $booking->service_zone_id) {
            return 50.0;
        }

        if ((int) $provider->primary_service_zone_id === (int) $booking->service_zone_id) {
            return 100.0;
        }

        $assignment = $provider->zoneAssignments
            ->firstWhere('service_zone_id', $booking->service_zone_id);

        return match ($assignment?->assignment_type) {
            'primary' => 90.0,
            'secondary' => 60.0,
            'backup' => 30.0,
            default => 0.0,
        };
    }

    protected function workloadScore(User $provider, Booking $booking): float
    {
        if (! $booking->date) {
            return 50.0;
        }

        $count = $provider->rendezVousEmploye()
            ->whereDate('date', $booking->date)
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->count();

        return match (true) {
            $count === 0 => 100.0,
            $count === 1 => 75.0,
            $count === 2 => 40.0,
            $count === 3 => 15.0,
            default => 0.0,
        };
    }

    protected function clientAffinityScore(User $provider, Booking $booking, array $context): float
    {
        $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
        if ($clientId <= 0) {
            return 50.0;
        }

        $favoriteBonus = 0.0;
        if (Schema::hasTable('client_provider_preferences')) {
            $query = DB::table('client_provider_preferences')
                ->where('provider_user_id', $provider->id);

            if (Schema::hasColumn('client_provider_preferences', 'client_user_id')) {
                $query->where('client_user_id', $clientId);
            } elseif (Schema::hasColumn('client_provider_preferences', 'client_id')) {
                $query->where('client_id', $clientId);
            }

            if (Schema::hasColumn('client_provider_preferences', 'is_favorite')) {
                $query->where('is_favorite', true);
            }

            if ($query->exists()) {
                $favoriteBonus = 50.0;
            }
        }

        $pastBookings = DB::table('bookings')
            ->where('client_id', $clientId)
            ->where('employe_id', $provider->id)
            ->whereIn('status', ['termine', 'completed', 'done'])
            ->count();

        $historyBonus = min(50.0, $pastBookings * 10);

        return min(100.0, $favoriteBonus + $historyBonus + 30);
    }

    protected function tradeScore(User $provider, Booking $booking): float
    {
        $tradeId = $booking->serviceCatalog?->trade_id;
        if (! $tradeId) {
            return 70.0;
        }

        $provider->loadMissing('trades:id,trade_user.proficiency');

        $trade = $provider->trades->firstWhere('id', $tradeId);
        if (! $trade) {
            return 0.0;
        }

        $proficiency = (string) ($trade->pivot->proficiency ?? '');
        $isPrimary = (bool) ($trade->pivot->is_primary ?? false);

        $base = match ($proficiency) {
            'expert' => 100.0,
            'advanced' => 85.0,
            'intermediate' => 70.0,
            'beginner' => 50.0,
            default => 70.0,
        };

        return $isPrimary ? min(100.0, $base + 10) : $base;
    }

    protected function recencyBalanceScore(array $context): float
    {
        $recent = (int) ($context['recent_missions_24h'] ?? 0);
        if (! Config::get('matching.diversification.enabled', true)) {
            return 50.0;
        }

        $penalty = (float) Config::get('matching.diversification.penalty_per_recent_mission', 2.0);
        return max(0.0, 100.0 - ($recent * $penalty * 10));
    }

    protected function recentMissionsCount(int $userId, int $hours): int
    {
        return (int) DB::table('bookings')
            ->where('employe_id', $userId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
    }
}
