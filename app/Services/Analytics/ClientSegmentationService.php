<?php

namespace App\Services\Analytics;

use App\Models\CustomerClaim;
use App\Models\RendezVous;
use App\Models\User;

class ClientSegmentationService
{
    public function segment(User $client): array
    {
        $bookings = RendezVous::query()
            ->where('client_id', $client->id)
            ->get();

        $totalBookings = $bookings->count();
        $completedBookings = $bookings->where('status', 'termine')->count();
        $totalRevenue = (float) $bookings->sum('devis_estime');

        $claimsCount = class_exists(CustomerClaim::class)
            ? CustomerClaim::where('client_id', $client->id)->count()
            : 0;

        $labels = [];

        if ($client->role === 'entreprise') {
            $labels[] = 'Entreprise';
        }

        if (method_exists($client, 'isPremium') && $client->isPremium()) {
            $labels[] = 'Premium';
        }

        if ($totalBookings >= 5) {
            $labels[] = 'Client fidèle';
        }

        if ($totalRevenue >= 1000) {
            $labels[] = 'Forte valeur';
        }

        if ($claimsCount >= 2) {
            $labels[] = 'Client à risque';
        }

        if ($totalBookings === 0) {
            $labels[] = 'Nouveau client';
        }

        if ($totalBookings > 0 && $bookings->max('date') && $bookings->max('date')->lt(now()->subMonths(3))) {
            $labels[] = 'Inactif';
        }

        if ($client->organizationSites()->count() > 1) {
            $labels[] = 'Multi-sites';
        }

        return [
            'client_id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'labels' => $labels,
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'total_revenue' => round($totalRevenue, 2),
            'claims_count' => $claimsCount,
            'risk_score' => $this->riskScore($claimsCount, $totalBookings),
            'loyalty_score' => $this->loyaltyScore($totalBookings, $totalRevenue),
        ];
    }

    protected function riskScore(int $claimsCount, int $totalBookings): int
    {
        if ($totalBookings === 0) {
            return 0;
        }

        return min(100, (int) round(($claimsCount / max(1, $totalBookings)) * 100));
    }

    protected function loyaltyScore(int $totalBookings, float $totalRevenue): int
    {
        return min(100, (int) round(($totalBookings * 10) + ($totalRevenue / 100)));
    }
}