<?php

namespace App\Services\Dispatch;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * MatchingScorer — lightweight weighted scoring for provider-to-booking matching.
 *
 * This is the public façade used by dispatch flows and APIs. For complex audit-trail
 * scoring use MatchingScoreEngine (Matching v2 module). MatchingScorer intentionally
 * stays dependency-free for easy unit testing.
 */
class MatchingScorer
{
    private const WEIGHTS = [
        'distance' => 0.30,
        'rating' => 0.25,
        'availability' => 0.20,
        'completion_rate' => 0.15,
        'price' => 0.10,
    ];

    /**
     * Score a collection of provider Users for the given Booking.
     *
     * @param  Collection<int, User>  $candidates
     * @return Collection<int, array{provider_id: int, provider_name: string, total_score: float, scores: array}>
     */
    public function scoreProviders(Booking $booking, Collection $candidates): Collection
    {
        return $candidates->map(function (User $provider) use ($booking) {
            $scores = [
                'distance' => $this->scoreDistance($booking, $provider),
                'rating' => $this->scoreRating($provider),
                'availability' => $this->scoreAvailability($provider),
                'completion_rate' => $this->scoreCompletionRate($provider),
                'price' => $this->scorePrice($booking, $provider),
            ];

            $weighted = collect($scores)
                ->map(fn ($score, $key) => $score * self::WEIGHTS[$key])
                ->sum();

            return [
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'total_score' => round($weighted, 4),
                'scores' => $scores,
            ];
        })->sortByDesc('total_score')->values();
    }

    // ─── Score factors ────────────────────────────────────────────────────────

    private function scoreDistance(Booking $booking, User $provider): float
    {
        /*
         * `current_lat` / `current_lng`, PAS `latitude` / `longitude`.
         *
         * Ces deux dernières n'existent pas sur `provider_profiles` : la lecture rendait donc
         * toujours nul, `$providerLat` valait toujours zéro, et le garde juste en dessous rendait
         * invariablement le score neutre de 0,5. LE FACTEUR DISTANCE DU SCORING N'A JAMAIS VARIÉ —
         * un prestataire à deux rues et un autre à quarante kilomètres recevaient la même note de
         * proximité, sur le repli employé quand Matching v2 échoue.
         *
         * Le défaut était masqué par une annotation fausse : le trait déclarait cette relation
         * comme rendant un `AvailabilitySlot`, si bien que l'analyse statique cherchait `latitude`
         * sur le mauvais modèle et n'y voyait rien d'anormal.
         */
        $providerLat = (float) ($provider->providerProfile->current_lat ?? 0);
        $providerLng = (float) ($provider->providerProfile->current_lng ?? 0);

        // Support both modern (destination_lat/lng) and legacy (latitude/longitude) columns
        $bookingLat = (float) ($booking->destination_lat ?? $booking->latitude ?? 0);
        $bookingLng = (float) ($booking->destination_lng ?? $booking->longitude ?? 0);

        if (! $providerLat || ! $bookingLat) {
            return 0.5; // neutral when coords unavailable
        }

        $distance = $this->haversine($bookingLat, $bookingLng, $providerLat, $providerLng);

        return (float) max(0.0, 1.0 - ($distance / 50.0)); // 0 km → 1.0, ≥50 km → 0.0
    }

    private function scoreRating(User $provider): float
    {
        $avg = (float) ($provider->providerProfile?->rating_avg ?? 3.0);

        return min(1.0, max(0.0, $avg / 5.0));
    }

    private function scoreAvailability(User $provider): float
    {
        // Check presence table (Presence v2 module)
        $presence = null;
        try {
            $presence = $provider->providerPresence ?? null;
        } catch (\Throwable) {
            // relation may not exist in all environments
        }

        if (! $presence) {
            return 0.3; // unknown → below neutral
        }

        return match ($presence->status) {
            'online' => 1.0,
            'on_break' => 0.5,
            'busy' => 0.3,
            'offline' => 0.0,
            default => 0.3,
        };
    }

    private function scoreCompletionRate(User $provider): float
    {
        try {
            $total = $provider->bookingsAsEmployee()->count();
            if ($total < 5) {
                return 0.5; // insufficient data — neutral
            }
            $completed = $provider->bookingsAsEmployee()
                ->whereIn('status', ['completed', 'termine', 'done'])
                ->count();

            return $total > 0 ? round($completed / $total, 4) : 0.5;
        } catch (\Throwable) {
            return 0.5;
        }
    }

    private function scorePrice(Booking $booking, User $provider): float
    {
        // Neutral placeholder — pricing engine integration deferred to PricingV2 module
        return 0.5;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Haversine great-circle distance in kilometres.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
    }
}
