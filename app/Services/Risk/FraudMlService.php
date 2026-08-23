<?php

namespace App\Services\Risk;

use App\Models\Booking;
use App\Models\User;

/** 7.4 — ML-based fraud scoring (replaces/augments the rule-based RiskScoringEngine). */
class FraudMlService
{
    /**
     * Score a booking for fraud risk using the ML model. TODO: implement
     *
     * @param  User  $user  Actor creating or paying for the booking
     * @param  Booking  $booking  Booking being evaluated
     * @return float|null Score in [0.0, 1.0] where 1.0 = certain fraud.
     *                    Null means model unavailable; fall back to rule engine.
     */
    public function score(User $user, Booking $booking): ?float
    {
        // TODO: extract feature vector, run inference
        return null; // soft-fail — callers use RiskScoringEngine as fallback
    }

    /**
     * Extract the feature vector for a (user, booking) pair.
     *
     * @return array<string, int|float|string>
     */
    public function featureVector(User $user, Booking $booking): array
    {
        return []; // TODO: implement feature extraction
    }
}
