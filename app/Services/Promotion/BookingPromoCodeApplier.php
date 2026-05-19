<?php

namespace App\Services\Promotion;

use App\Models\Booking;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BookingPromoCodeApplier
{
    public function __construct(protected PromoCodeService $promoCodeService)
    {
    }

    /**
     * Apply a promo code to a freshly-created booking.
     * Throws ValidationException on invalid code; silently skips when empty.
     *
     * Returns the redemption if applied, null otherwise.
     */
    public function applyToBooking(Booking $booking, User $client, ?string $rawCode): ?PromoCodeRedemption
    {
        $code = trim((string) $rawCode);
        if ($code === '') {
            return null;
        }

        $amount = (float) ($booking->devis_estime ?? $booking->estimated_price ?? 0);

        if ($amount <= 0) {
            return null;
        }

        $countryId = data_get($booking->zone_snapshot, 'country_id');
        if ($countryId !== null) {
            $countryId = (int) $countryId;
        }

        $isFirstBooking = ! Booking::query()
            ->where('client_id', $client->id)
            ->where('id', '!=', $booking->id)
            ->exists();

        $isB2B = (bool) ($booking->organization_account_id ?? $booking->customer_organization_id ?? null);

        $context = new PromoCodeValidationContext(
            user: $client,
            bookingAmount: $amount,
            tradeId: $this->resolveTradeId($booking),
            serviceCatalogId: $booking->service_catalog_id ? (int) $booking->service_catalog_id : null,
            countryId: $countryId,
            serviceZoneId: $booking->service_zone_id ? (int) $booking->service_zone_id : null,
            isFirstBooking: $isFirstBooking,
            isB2B: $isB2B,
            currency: data_get($booking->pricing_snapshot, 'currency', 'EUR'),
        );

        $result = $this->promoCodeService->validate($code, $context);

        if (! $result->valid || ! $result->promoCode) {
            throw ValidationException::withMessages([
                'promo_code' => $result->reason ?? 'Code promo invalide.',
            ]);
        }

        $redemption = $this->promoCodeService->apply($result->promoCode, $client, $booking, $amount);

        $this->writeBackToBooking($booking, $redemption);

        return $redemption;
    }

    protected function writeBackToBooking(Booking $booking, PromoCodeRedemption $redemption): void
    {
        $booking->devis_estime = round(max(0, (float) $redemption->booking_amount_after), 2);
        $booking->estimated_price = $booking->devis_estime;

        $snapshot = (array) ($booking->pricing_snapshot ?? []);
        $snapshot['promo_code_applied'] = [
            'redemption_id' => $redemption->id,
            'promo_code_id' => $redemption->promo_code_id,
            'code' => $redemption->promoCode?->code,
            'discount_amount' => (float) $redemption->discount_amount,
            'amount_before' => (float) $redemption->booking_amount_before,
            'amount_after' => (float) $redemption->booking_amount_after,
        ];
        $booking->pricing_snapshot = $snapshot;

        $booking->save();
    }

    protected function resolveTradeId(Booking $booking): ?int
    {
        $catalog = $booking->serviceCatalog;
        if (! $catalog) {
            return null;
        }

        return $catalog->trade_id ? (int) $catalog->trade_id : null;
    }
}
