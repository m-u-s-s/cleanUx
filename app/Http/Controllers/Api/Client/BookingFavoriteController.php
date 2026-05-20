<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Services\Bookings\BookingFavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = BookingFavorite::query()
            ->where('client_user_id', $request->user()->id)
            ->with(['provider:id,name', 'trade:id,name'])
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $items->map(fn (BookingFavorite $f) => [
                'id' => $f->id,
                'label' => $f->label,
                'preferred_provider' => $f->provider ? [
                    'id' => $f->provider->id,
                    'name' => $f->provider->name,
                ] : null,
                'trade' => $f->trade ? [
                    'id' => $f->trade->id,
                    'name' => $f->trade->name,
                ] : null,
                'snapshot' => $f->snapshot,
                'use_count' => (int) $f->use_count,
                'last_used_at' => $f->last_used_at,
                'created_at' => $f->created_at,
            ]),
        ]);
    }

    public function create(Request $request, Booking $booking, BookingFavoriteService $service): JsonResponse
    {
        $params = $request->validate([
            'label' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            $favorite = $service->createFromBooking(
                $request->user(),
                $booking,
                $params['label'] ?? null,
            );
            return response()->json(['data' => ['id' => $favorite->id, 'label' => $favorite->label]], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }

    public function markUsed(Request $request, BookingFavorite $favorite, BookingFavoriteService $service): JsonResponse
    {
        if ((int) $favorite->client_user_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $service->markUsed($favorite);
        return response()->json(['data' => ['use_count' => (int) $favorite->fresh()->use_count]]);
    }

    public function destroy(Request $request, BookingFavorite $favorite, BookingFavoriteService $service): JsonResponse
    {
        if ((int) $favorite->client_user_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $service->delete($favorite);
        return response()->json(['data' => ['deleted' => true]]);
    }
}
