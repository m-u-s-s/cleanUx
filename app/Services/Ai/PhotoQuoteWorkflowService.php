<?php

namespace App\Services\Ai;

use App\Models\Booking;
use App\Models\Trade;
use App\Models\User;

/**
 * 7.10 — AI photo-based quote workflow orchestrator.
 *
 * PhotoQuoteEstimator (already implemented) provides the Claude Vision
 * inference layer. This service orchestrates the full booking-creation
 * workflow that follows an AI estimate.
 *
 * Architecture plan:
 *   1. Client uploads 1-4 photos + selects trade.
 *   2. PhotoQuoteEstimator returns { prix_min, prix_max, confiance }.
 *   3. If confiance >= threshold, offer a "locked price" booking.
 *   4. If confiance < threshold, offer a "price-on-site" booking + note.
 *   5. Client accepts → Booking created with pricing_snapshot containing
 *      the quote + photo hashes (for audit).
 *   6. Quote stored in photo_quotes table for retraining feedback loop.
 *
 * TODO: create photo_quotes table (id, user_id, trade_id, booking_id,
 *       photo_hashes json, quote_result json, confiance, used_at, created_at)
 * TODO: implement createBookingFromQuote()
 * TODO: implement quote expiry (15min) via signed URL or quote_token
 * TODO: implement feedback loop — after mission, compare quoted vs actual duration
 * TODO: store training data: photo + actual outcome for model fine-tuning
 */
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
     *
     * TODO: implement multi-photo aggregation (average prices, min confidence)
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

        $confidenceThreshold = (int) config('cleanux.photo_quote_confidence_threshold', 60);
        $result['booking_ready'] = ($result['confiance'] ?? 0) >= $confidenceThreshold;

        // TODO: persist to photo_quotes table for audit + retraining
        return $result;
    }

    /**
     * Create a booking pre-populated with quote data.
     *
     * TODO: implement
     */
    public function createBookingFromQuote(array $quote, User $user, array $bookingData): Booking
    {
        throw new \RuntimeException('[PhotoQuoteWorkflowService] createBookingFromQuote not implemented — TODO.');
    }
}
