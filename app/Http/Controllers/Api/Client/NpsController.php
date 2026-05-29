<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\NpsResponse;
use App\Services\Nps\NpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Client — NPS Survey
 *
 * @authenticated
 */
class NpsController extends Controller
{
    public function submit(Request $request, NpsService $service): JsonResponse
    {
        $data = $request->validate([
            'survey_code' => ['required', 'string', 'in:post_booking,monthly,annual,onboarding,churn'],
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        $booking = null;
        if (! empty($data['booking_id'])) {
            $booking = Booking::query()->find($data['booking_id']);
            if ($booking && (int) $booking->client_id !== (int) $request->user()->id) {
                return response()->json(['error' => 'forbidden'], 403);
            }
        }

        try {
            $response = $service->submit(
                user: $request->user(),
                surveyCode: $data['survey_code'],
                score: (int) $data['score'],
                booking: $booking,
                comment: $data['comment'] ?? null,
            );

            return response()->json([
                'data' => [
                    'id' => $response->id,
                    'score' => (int) $response->score,
                    'category' => $response->category,
                    'survey_code' => $response->survey_code,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $items = NpsResponse::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('responded_at')
            ->limit(20)
            ->get(['id', 'survey_code', 'score', 'category', 'comment', 'responded_at']);

        return response()->json(['data' => $items]);
    }
}
