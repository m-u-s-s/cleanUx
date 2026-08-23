<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\StoreRatingRequest;
use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Rating\RatingModerationService;
use App\Services\Rating\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Ratings
 *
 * @authenticated
 */
class RatingController extends Controller
{
    /**
     * Submit a rating for a completed booking.
     *
     * @bodyParam rating integer required Overall star rating (1-5). Example: 5
     * @bodyParam comment string Optional written review (max 2000 chars). Example: Excellent travail, très ponctuel.
     * @bodyParam punctuality integer Optional sub-rating for punctuality (1-5). Example: 5
     * @bodyParam quality integer Optional sub-rating for quality of work (1-5). Example: 4
     * @bodyParam communication integer Optional sub-rating for communication (1-5). Example: 5
     * @bodyParam value integer Optional sub-rating for value for money (1-5). Example: 4
     * @bodyParam is_public boolean Whether the review is publicly visible (default true). Example: 1
     *
     * @response 201 {"feedback_id": 7, "status": "pending_reveal", "published_at": null}
     * @response 422 {"message": "The rating field is required.", "errors": {"rating": ["The rating field is required."]}}
     */
    public function submit(StoreRatingRequest $request, Booking $booking): JsonResponse
    {
        $data = $request->validated();

        $feedback = app(RatingService::class)->submit(
            booking: $booking,
            author: $request->user(),
            direction: Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            payload: $data,
        );

        return response()->json([
            'feedback_id' => $feedback->id,
            'status' => $feedback->status,
            'published_at' => $feedback->published_at,
        ], 201);
    }

    /**
     * Report a review for moderation.
     *
     * @bodyParam reason string required Short moderation reason (max 64 chars). Example: inappropriate
     * @bodyParam details string Optional additional context (max 1000 chars). Example: Le commentaire contient des insultes.
     *
     * @response 201 {"report_id": 3, "status": "pending"}
     */
    public function report(Request $request, Feedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = app(RatingModerationService::class)->report(
            $feedback,
            $request->user(),
            $data['reason'],
            $data['details'] ?? null,
        );

        return response()->json([
            'report_id' => $report->id,
            'status' => $report->status,
        ], 201);
    }
}
