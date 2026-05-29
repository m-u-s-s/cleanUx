<?php

namespace App\Services\Risk;

use App\Models\Booking;
use App\Models\User;

/**
 * 7.4 — ML-based fraud scoring (replaces/augments the rule-based RiskScoringEngine).
 *
 * Interface defined; model training and inference pipeline deferred.
 *
 * Architecture plan:
 *   - Feature vector: velocity, geo-anomaly, device fingerprint, historical
 *     decline rate, booking amount z-score, time-of-day, new-account age.
 *   - Training: Python/scikit-learn job consuming analytics_events + bookings.
 *     Serialise model to ONNX → ship in storage/ml/fraud_model.onnx.
 *   - Inference: PHP via php-onnxruntime extension OR HTTP microservice
 *     (FastAPI endpoint, call via Http::post()).
 *   - Fallback: if model absent or inference fails, return null (caller uses
 *     rule-based score from RiskScoringEngine).
 *
 * TODO: train initial model on historical fraud labels
 * TODO: implement FraudMlProvider contract + OnnxFraudProvider + HttpFraudProvider
 * TODO: A/B shadow-mode: run ML score alongside rule-based, compare in analytics
 * TODO: add fraud_ml_scores table for audit / retraining feedback loop
 */
class FraudMlService
{
    /**
     * Score a booking for fraud risk using the ML model.
     *
     * @param  User  $user  Actor creating or paying for the booking
     * @param  Booking  $booking  Booking being evaluated
     * @return float|null Score in [0.0, 1.0] where 1.0 = certain fraud.
     *                    Null means model unavailable; fall back to rule engine.
     *
     * TODO: implement
     */
    public function score(User $user, Booking $booking): ?float
    {
        // TODO: extract feature vector, run inference
        return null; // soft-fail — callers use RiskScoringEngine as fallback
    }

    /**
     * Extract the feature vector for a (user, booking) pair.
     * Useful for debugging / explainability endpoint.
     *
     * @return array<string, int|float|string>
     *
     * TODO: implement
     */
    public function featureVector(User $user, Booking $booking): array
    {
        return []; // TODO: implement feature extraction
    }
}
