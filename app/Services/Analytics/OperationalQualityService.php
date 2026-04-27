<?php

namespace App\Services\Analytics;

use App\Models\CustomerClaim;
use App\Models\Feedback;
use App\Models\Mission;
use App\Models\RendezVous;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;

class OperationalQualityService
{
    public function metrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $missions = Mission::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('planned_start_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('planned_start_at', '<=', $dateTo))
            ->get();

        $bookings = RendezVous::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('date', '<=', $dateTo))
            ->get();

        $claims = class_exists(CustomerClaim::class)
            ? CustomerClaim::query()
                ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->get()
            : collect();

        $feedbacks = Feedback::query()
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->get();

        $completedMissions = $missions->where('status', MissionStatus::COMPLETED);

        $onTimeMissions = $completedMissions->filter(function ($mission) {
            if (! $mission->planned_start_at || ! $mission->actual_start_at) {
                return false;
            }

            return $mission->actual_start_at->lessThanOrEqualTo(
                $mission->planned_start_at->copy()->addMinutes(10)
            );
        });

        $resolvedClaims = $claims->filter(fn ($claim) => $claim->resolved_at);

        return [
            'missions_total' => $missions->count(),
            'missions_completed' => $completedMissions->count(),
            'punctuality_rate' => $this->rate($onTimeMissions->count(), $completedMissions->count()),

            'bookings_total' => $bookings->count(),
            'bookings_cancelled' => $bookings->where('status', BookingStatus::REFUSE)->count(),
            'no_show_rate' => $this->rate(
                $missions->where('status', MissionStatus::CANCELLED)->count(),
                $missions->count()
            ),

            'replanning_count' => $this->countReplannedBookings($bookings),
            'replanning_rate' => $this->rate($this->countReplannedBookings($bookings), $bookings->count()),

            'claims_total' => $claims->count(),
            'claims_open' => $claims->whereIn('status', ['open', 'in_review', 'waiting_client'])->count(),
            'claims_resolved' => $resolvedClaims->count(),
            'avg_claim_resolution_hours' => $this->avgClaimResolutionHours($resolvedClaims),

            'feedback_total' => $feedbacks->count(),
            'avg_rating' => round((float) $feedbacks->avg('note'), 1),
            'csat_rate' => $this->rate($feedbacks->where('note', '>=', 4)->count(), $feedbacks->count()),

            'quality_score_avg' => round((float) $completedMissions->avg('quality_score'), 1),
        ];
    }

    protected function rate(int|float $value, int|float $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }

    protected function countReplannedBookings($bookings): int
    {
        return $bookings->filter(function ($booking) {
            return $booking->rappel_24h_envoye_at === null
                && $booking->updated_at
                && $booking->created_at
                && $booking->updated_at->gt($booking->created_at->copy()->addMinutes(5));
        })->count();
    }

    protected function avgClaimResolutionHours($claims): float
    {
        if ($claims->count() === 0) {
            return 0;
        }

        return round($claims->avg(function ($claim) {
            return $claim->created_at->diffInHours($claim->resolved_at);
        }), 1);
    }
}