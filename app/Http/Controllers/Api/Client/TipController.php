<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Services\Tips\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TipController extends Controller
{
    public function suggestions(Request $request, Booking $booking, TipService $service): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'data' => $service->suggestionsForBooking($booking),
        ]);
    }

    public function create(Request $request, Booking $booking, TipService $service): JsonResponse
    {
        $params = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:100', 'max:50000'],
            'preset_label' => ['nullable', 'string', 'max:16'],
            'preset_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'message' => ['nullable', 'string', 'max:280'],
        ]);

        try {
            $tip = $service->create(
                client: $request->user(),
                booking: $booking,
                amountCents: (int) $params['amount_cents'],
                presetLabel: $params['preset_label'] ?? null,
                presetPercent: isset($params['preset_percent']) ? (int) $params['preset_percent'] : null,
                message: $params['message'] ?? null,
            );

            return response()->json([
                'data' => $this->presentTip($tip),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function mine(Request $request): JsonResponse
    {
        $params = $request->validate([
            'status' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = BookingTip::query()
            ->where('client_user_id', $request->user()->id)
            ->with(['booking:id,date_debut,trade_id', 'provider:id,name'])
            ->orderByDesc('created_at');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $items = $query->limit($params['limit'] ?? 50)->get();

        return response()->json([
            'data' => $items->map(fn (BookingTip $t) => $this->presentTip($t)),
        ]);
    }

    protected function presentTip(BookingTip $tip): array
    {
        return [
            'id' => $tip->id,
            'code' => $tip->code,
            'booking_id' => (int) $tip->booking_id,
            'provider' => $tip->provider ? [
                'id' => $tip->provider->id,
                'name' => $tip->provider->name,
            ] : null,
            'amount_cents' => (int) $tip->amount_cents,
            'currency' => $tip->currency,
            'status' => $tip->status,
            'message' => $tip->message,
            'preset_label' => $tip->preset_label,
            'client_bonus_points' => (int) $tip->client_bonus_points,
            'charged_at' => $tip->charged_at,
            'paid_out_at' => $tip->paid_out_at,
            'created_at' => $tip->created_at,
        ];
    }
}
