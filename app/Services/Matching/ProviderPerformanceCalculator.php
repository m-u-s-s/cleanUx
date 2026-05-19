<?php

namespace App\Services\Matching;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\MissionAssignment;
use App\Models\ProviderPerformanceMetric;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderPerformanceCalculator
{
    public const DEFAULT_WINDOW_DAYS = 30;

    public function calculate(User $provider, int $windowDays = self::DEFAULT_WINDOW_DAYS): ProviderPerformanceMetric
    {
        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($windowDays)->startOfDay();

        $offers = $this->offersStats($provider, $start, $end);
        $missions = $this->missionsStats($provider, $start, $end);
        $rating = $this->ratingStats($provider, $start, $end);

        $offersReceived = (int) $offers['received'];
        $offersAccepted = (int) $offers['accepted'];
        $offersDeclined = (int) $offers['declined'];
        $offersExpired = (int) $offers['expired'];

        $acceptanceRate = $offersReceived > 0
            ? round($offersAccepted / $offersReceived, 4)
            : null;

        $completionRate = $offersAccepted > 0
            ? round((int) $missions['completed'] / $offersAccepted, 4)
            : null;

        $cancellationRate = $offersAccepted > 0
            ? round((int) $missions['cancelled_by_provider'] / $offersAccepted, 4)
            : null;

        $payload = [
            'user_id' => $provider->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'window_days' => $windowDays,
            'offers_received' => $offersReceived,
            'offers_accepted' => $offersAccepted,
            'offers_declined' => $offersDeclined,
            'offers_expired' => $offersExpired,
            'missions_completed' => (int) $missions['completed'],
            'missions_cancelled_by_provider' => (int) $missions['cancelled_by_provider'],
            'acceptance_rate' => $acceptanceRate,
            'completion_rate' => $completionRate,
            'cancellation_rate' => $cancellationRate,
            'avg_response_seconds' => $offers['avg_response_seconds'],
            'rating_avg_window' => $rating['avg'],
            'rating_count_window' => (int) $rating['count'],
            'computed_at' => now(),
        ];

        $existing = ProviderPerformanceMetric::query()
            ->where('user_id', $provider->id)
            ->whereDate('period_end', $end->toDateString())
            ->first();

        if ($existing) {
            $existing->fill($payload)->save();
            return $existing->fresh();
        }

        return ProviderPerformanceMetric::create($payload);
    }

    public function latestFor(int $userId): ?ProviderPerformanceMetric
    {
        return ProviderPerformanceMetric::query()
            ->where('user_id', $userId)
            ->latest('period_end')
            ->first();
    }

    /**
     * @return array{received:int, accepted:int, declined:int, expired:int, avg_response_seconds:?int}
     */
    protected function offersStats(User $provider, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('mission_assignments')) {
            return ['received' => 0, 'accepted' => 0, 'declined' => 0, 'expired' => 0, 'avg_response_seconds' => null];
        }

        $base = MissionAssignment::query()
            ->where('user_id', $provider->id)
            ->whereBetween('assigned_at', [$start, $end]);

        $statusColumn = Schema::hasColumn('mission_assignments', 'assignment_status')
            ? 'assignment_status'
            : 'status';

        $received = (clone $base)->count();
        $accepted = (clone $base)->whereNotNull('accepted_at')->count();
        $declined = (clone $base)->whereNotNull('declined_at')->count();
        $expired = (clone $base)
            ->where(function ($q) use ($statusColumn) {
                $q->where($statusColumn, 'expired')
                    ->orWhere($statusColumn, 'timeout');
            })
            ->count();

        $avgResponse = null;
        if (Schema::hasColumn('mission_assignments', 'response_seconds')) {
            $avg = (float) (clone $base)
                ->whereNotNull('response_seconds')
                ->avg('response_seconds');
            $avgResponse = $avg > 0 ? (int) round($avg) : null;
        }

        return [
            'received' => $received,
            'accepted' => $accepted,
            'declined' => $declined,
            'expired' => $expired,
            'avg_response_seconds' => $avgResponse,
        ];
    }

    /**
     * @return array{completed:int, cancelled_by_provider:int}
     */
    protected function missionsStats(User $provider, Carbon $start, Carbon $end): array
    {
        $base = Booking::query()
            ->where('employe_id', $provider->id)
            ->whereBetween('updated_at', [$start, $end]);

        $completed = (clone $base)
            ->whereIn('status', ['termine', 'completed', 'done'])
            ->count();

        $cancelledByProvider = 0;
        if (Schema::hasColumn('bookings', 'cancelled_by')) {
            $cancelledByProvider = (clone $base)
                ->whereIn('status', ['annule', 'cancelled', 'refused'])
                ->where('cancelled_by', $provider->id)
                ->count();
        }

        return [
            'completed' => $completed,
            'cancelled_by_provider' => $cancelledByProvider,
        ];
    }

    /**
     * @return array{avg:?float, count:int}
     */
    protected function ratingStats(User $provider, Carbon $start, Carbon $end): array
    {
        if (! Schema::hasTable('feedback')) {
            return ['avg' => null, 'count' => 0];
        }

        $base = Feedback::query()
            ->where('employe_id', $provider->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->where('status', Feedback::STATUS_PUBLISHED)
            ->where('is_hidden', false)
            ->where('is_public', true)
            ->whereBetween('published_at', [$start, $end]);

        $count = (clone $base)->count();
        if ($count === 0) {
            return ['avg' => null, 'count' => 0];
        }

        $sum = (float) (clone $base)->sum(DB::raw('COALESCE(rating, note)'));
        $avg = $count > 0 ? round($sum / $count, 2) : null;

        return [
            'avg' => $avg,
            'count' => $count,
        ];
    }
}
