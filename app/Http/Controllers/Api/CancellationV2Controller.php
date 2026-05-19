<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingCancellationV2;
use App\Services\CancellationV2\CancellationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Unified cancellation v2 controller — sert client / provider / admin via
 * une seule classe et discrimine par actor_role. Routes API séparées appellent
 * chacune leur méthode dédiée pour clarté.
 */
class CancellationV2Controller extends Controller
{
    public function __construct(protected CancellationEngine $engine)
    {
    }

    public function quote(Request $request, int $booking, string $actorRole): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $quote = $this->engine->quote(
                bookingId: $booking,
                actorRole: $actorRole,
                reasonCode: $data['reason_code'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'quote' => $quote->toArray()]);
    }

    public function execute(Request $request, int $booking, string $actorRole): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['nullable', 'string', 'max:64'],
            'reason_text' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $row = $this->engine->execute(
                bookingId: $booking,
                actor: $request->user(),
                actorRole: $actorRole,
                reasonCode: $data['reason_code'] ?? null,
                reasonText: $data['reason_text'] ?? null,
                idempotencyKey: $data['idempotency_key'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'cancellation' => $row,
        ], 201);
    }

    public function clientQuote(Request $request, int $booking): JsonResponse
    {
        return $this->quote($request, $booking, 'client');
    }

    public function clientExecute(Request $request, int $booking): JsonResponse
    {
        return $this->execute($request, $booking, 'client');
    }

    public function providerQuote(Request $request, int $booking): JsonResponse
    {
        return $this->quote($request, $booking, 'provider');
    }

    public function providerExecute(Request $request, int $booking): JsonResponse
    {
        return $this->execute($request, $booking, 'provider');
    }

    public function adminOverride(Request $request, BookingCancellationV2 $cancellation): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $row = $this->engine->override($cancellation, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'cancellation' => $row]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $rows = BookingCancellationV2::query()
            ->with(['policy:id,code,name', 'cancelledBy:id,email,name'])
            ->when($request->filled('actor_role'), fn ($q) => $q->where('actor_role', $request->string('actor_role')))
            ->when($request->filled('booking_id'), fn ($q) => $q->where('booking_id', $request->integer('booking_id')))
            ->orderByDesc('cancelled_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }
}
