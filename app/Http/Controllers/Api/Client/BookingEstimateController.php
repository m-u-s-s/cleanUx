<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ServiceCatalog;
use App\Services\Pricing\TradePricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/client/bookings/estimate
 *
 * Returns a price estimate for a service before the client commits to booking.
 * No booking record is created — purely read-only.
 *
 * @group Client Bookings
 *
 * @authenticated
 */
class BookingEstimateController extends Controller
{
    public function __construct(private readonly TradePricingEngine $engine) {}

    /**
     * Estimate booking price.
     *
     * @bodyParam service_catalog_id int required The service to estimate. Example: 3
     * @bodyParam trade_form_answers object Optional form answers (duration_hours, surface_m2, etc.). Example: {"duration_hours":3}
     * @bodyParam service_zone_id int optional Service zone for zone-based pricing. Example: 1
     *
     * @response 200 {"ok":true,"data":{"billing_unit":"hourly","unit_price":25.0,"quantity":3.0,"surge_multiplier":1.0,"subtotal":75.0,"currency":"EUR","zone_pricing_applied":false}}
     * @response 422 {"message":"The service catalog id field is required."}
     * @response 404 {"message":"Service not found."}
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_catalog_id' => ['required', 'integer', 'min:1'],
            'trade_form_answers' => ['sometimes', 'array'],
            'service_zone_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $service = ServiceCatalog::with('trade')->find($validated['service_catalog_id']);

        if (! $service || ! $service->is_active) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $estimate = $this->engine->estimate(
            $service,
            $validated['trade_form_answers'] ?? [],
            $validated['service_zone_id'] ?? null,
        );

        return response()->json(['ok' => true, 'data' => $estimate]);
    }
}
