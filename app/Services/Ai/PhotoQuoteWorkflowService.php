<?php

namespace App\Services\Ai;

use App\Models\Booking;
use App\Models\Trade;
use App\Models\User;

/** 7.10 — AI photo-based quote workflow orchestrator. */
class PhotoQuoteWorkflowService
{
    public function __construct(protected PhotoQuoteEstimator $estimator) {}

    /**
     * Run the full estimate-to-quote workflow for multiple photos.
     *
     * @param  string[]  $base64Images  Array of base64-encoded images (max 4)
     * @param  Trade  $trade  The service trade
     * @param  User  $user  Requesting user
     * @param  string|null  $note  Optional client note
     * @return array Combined quote with confidence & booking readiness flag
     */
    public function estimateFromPhotos(
        array $base64Images,
        Trade $trade,
        User $user,
        ?string $note = null,
    ): array {
        if (empty($base64Images)) {
            return ['success' => false, 'error' => 'no_photos'];
        }

        // Single-photo path — multi-photo aggregation TODO
        $result = $this->estimator->estimateFromPhoto($base64Images[0], $trade, $note);
        if ($result === null) {
            return ['success' => false, 'error' => 'estimator_unavailable'];
        }

        $confidenceThreshold = (int) config('brio.photo_quote_confidence_threshold', 60);
        $result['booking_ready'] = ($result['confiance'] ?? 0) >= $confidenceThreshold;

        // TODO: persist to photo_quotes table for audit + retraining
        return $result;
    }

    /** Create a booking pre-populated with quote data. TODO: implement */
    public function createBookingFromQuote(array $quote, User $user, array $bookingData): Booking
    {
        throw new \RuntimeException('[PhotoQuoteWorkflowService] createBookingFromQuote not implemented — TODO.');
    }
}
