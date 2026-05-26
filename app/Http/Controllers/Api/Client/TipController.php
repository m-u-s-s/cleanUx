<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\CreateTipRequest;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Services\Tips\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Tips
 * @authenticated
 */
class TipController extends Controller
{
    /**
     * Get tip amount suggestions for a booking.
     *
     * Returns three preset percentage options based on the booking's total amount.
     *
     * @response 200 {"data": [{"label": "10%", "preset_percent": 10, "amount_cents": 500}, {"label": "15%", "preset_percent": 15, "amount_cents": 750}, {"label": "20%", "preset_percent": 20, "amount_cents": 1000}]}
     * @response 403 {"error": "forbidden"}
     */
    public function suggestions(Request $request, Booking $booking, TipService $service): JsonResponse
    {
        if ((int) $booking->client_id !== (int) $request->user()->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        return response()->json([
            'data' => $service->suggestionsForBooking($booking),
        ]);
    }

    /**
     * Create a tip for a completed booking.
     *
     * Idempotent: a second call for the same booking will be rejected with 422 if a non-final tip already exists.
     * Client earns 1 loyalty point per euro tipped (soft-fail).
     *
     * @bodyParam amount_cents integer required Tip amount in euro-cents (100-50000). Example: 500
     * @bodyParam preset_label string Optional label for the chosen preset (max 16 chars). Example: 10%
     * @bodyParam preset_percent integer Optional preset percentage applied (1-100). Example: 10
     * @bodyParam message string Optional personal message to the provider (max 280 chars). Example: Super travail, merci!
     * @response 201 {"data": {"id": 3, "code": "TIP-ABC123", "booking_id": 42, "provider": {"id": 7, "name": "Jean Martin"}, "amount_cents": 500, "currency": "EUR", "status": "pending", "message": "Super travail, merci!", "preset_label": "10%", "client_bonus_points": 5, "charged_at": null, "paid_out_at": null, "created_at": "2026-06-15T11:00:00+00:00"}}
     * @response 422 {"error": "validation_failed", "errors": {"amount_cents": ["Un pourboire existe déjà pour cette réservation."]}}
     */
    public function create(CreateTipRequest $request, Booking $booking, TipService $service): JsonResponse
    {
        $params = $request->validated();

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

    /**
     * List all tips created by the authenticated client.
     *
     * @queryParam status string Filter by tip status (pending, charged, paid_out, cancelled, failed). Example: charged
     * @queryParam limit integer Maximum number of results (1-100, default 50). Example: 20
     * @response 200 {"data": [{"id": 3, "code": "TIP-ABC123", "booking_id": 42, "provider": {"id": 7, "name": "Jean Martin"}, "amount_cents": 500, "currency": "EUR", "status": "charged", "message": null, "preset_label": "10%", "client_bonus_points": 5, "charged_at": "2026-06-15T12:00:00+00:00", "paid_out_at": null, "created_at": "2026-06-15T11:00:00+00:00"}]}
     */
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
