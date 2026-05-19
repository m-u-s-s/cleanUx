<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Rating\ProviderResponseService;
use App\Services\Rating\RatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderRatingController extends Controller
{
    public function submit(Request $request, Booking $booking): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        $feedback = app(RatingService::class)->submit(
            booking: $booking,
            author: $request->user(),
            direction: Feedback::DIRECTION_PROVIDER_TO_CLIENT,
            payload: $data,
        );

        return response()->json([
            'feedback_id' => $feedback->id,
            'status' => $feedback->status,
            'published_at' => $feedback->published_at,
        ], 201);
    }

    public function respond(Request $request, Feedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'response' => ['required', 'string', 'max:1000'],
        ]);

        $feedback = app(ProviderResponseService::class)->reply(
            $feedback,
            $request->user(),
            $data['response'],
        );

        return response()->json([
            'feedback_id' => $feedback->id,
            'provider_response' => $feedback->provider_response,
            'provider_responded_at' => $feedback->provider_responded_at,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $providerId = $request->user()->id;

        $params = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ratings = Feedback::query()
            ->forProvider($providerId)
            ->with(['client:id,name'])
            ->latest('published_at')
            ->latest('id')
            ->limit($params['limit'] ?? 20)
            ->get();

        return response()->json([
            'data' => $ratings->map(fn (Feedback $f) => [
                'id' => $f->id,
                'rating' => (int) ($f->rating ?? $f->note),
                'comment' => $f->effectiveComment(),
                'client_name' => $f->client?->name,
                'status' => $f->status,
                'is_hidden' => (bool) $f->is_hidden,
                'published_at' => $f->published_at,
                'provider_response' => $f->provider_response,
                'reports_count' => (int) $f->reports_count,
            ]),
        ]);
    }
}
