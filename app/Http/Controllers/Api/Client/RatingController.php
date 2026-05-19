<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Rating\RatingModerationService;
use App\Services\Rating\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function submit(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'punctuality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_public' => ['boolean'],
        ]);

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
