<?php

namespace App\Services\Admin;

use App\Models\Mission;
use App\Models\Booking;

class AdminAlertService
{
    public function alerts(): array
    {
        return [
            'late_missions' => $this->lateMissions(),
            'not_started_soon' => $this->notStartedSoon(),
            'tracking_inactive' => $this->trackingInactive(),
            'payment_not_captured' => $this->paymentNotCaptured(),
        ];
    }

    protected function lateMissions()
    {
        return Mission::query()
            ->with(['rendezVous.client', 'leadEmployee'])
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '<', now()->subMinutes(15))
            ->whereNotIn('status', ['started', 'completed', 'cancelled'])
            ->latest()
            ->take(10)
            ->get();
    }

    protected function notStartedSoon()
    {
        return Mission::query()
            ->with(['rendezVous.client', 'leadEmployee'])
            ->whereBetween('planned_start_at', [now(), now()->addMinutes(30)])
            ->whereIn('status', ['assigned', 'confirme'])
            ->latest()
            ->take(10)
            ->get();
    }

    protected function trackingInactive()
    {
        return Mission::query()
            ->with(['rendezVous.client', 'leadEmployee', 'activeTrackingSession'])
            ->where('status', 'en_route')
            ->whereDoesntHave('activeTrackingSession')
            ->latest()
            ->take(10)
            ->get();
    }

    protected function paymentNotCaptured()
    {
        return Booking::query()
            ->with(['client', 'employe', 'mission'])
            ->whereHas('mission', fn ($q) => $q->where('status', 'completed'))
            ->where('payment_status', 'authorized')
            ->latest()
            ->take(10)
            ->get();
    }
}