<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Promotion\PromoCodeService;
use App\Services\Promotion\PromoCodeValidationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mobile — Validation d'un code promo.
 *
 * POST /api/client/promo-codes/validate
 *   { code: "SUMMER25", amount: 120.00, service_catalog_id?: 5, service_zone_id?: 12 }
 *
 * Retourne valid + discount + final amount, sans rien persister.
 */
class PromoCodeController extends Controller
{
    public function __construct(protected PromoCodeService $service)
    {
    }

    public function validate_(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'service_catalog_id' => ['nullable', 'integer'],
            'service_zone_id' => ['nullable', 'integer'],
            'country_id' => ['nullable', 'integer'],
            'trade_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $isFirstBooking = ! Booking::query()
            ->where('client_id', $user->id)
            ->exists();

        $isB2B = (bool) ($user->organization_account_id ?? $user->current_organization_id ?? null);

        $context = new PromoCodeValidationContext(
            user: $user,
            bookingAmount: (float) $data['amount'],
            tradeId: $data['trade_id'] ?? null,
            serviceCatalogId: $data['service_catalog_id'] ?? null,
            countryId: $data['country_id'] ?? null,
            serviceZoneId: $data['service_zone_id'] ?? null,
            isFirstBooking: $isFirstBooking,
            isB2B: $isB2B,
        );

        $result = $this->service->validate($data['code'], $context);

        return response()->json([
            'valid' => $result->valid,
            'error_code' => $result->errorCode,
            'reason' => $result->reason,
            'discount_amount' => $result->discountAmount,
            'final_amount' => $result->finalAmount,
            'code' => $result->promoCode?->code,
            'discount_type' => $result->promoCode?->discount_type,
            'discount_value' => $result->promoCode !== null ? (float) $result->promoCode->discount_value : null,
        ], $result->valid ? 200 : 422);
    }
}
