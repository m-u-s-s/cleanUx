<?php

namespace App\Services\Insurance;

use App\Models\InsurancePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * InsurancePricingEngine — calcule la prime due pour un plan + booking.
 *
 * Formule :
 *   premium = clamp(base + percent × booking_amount, min, max)
 *
 * Sélection des plans :
 *   getAvailablePlansForBooking(bookingId) retourne les plans actifs
 *   compatibles avec le trade du booking + validité temporelle.
 */
class InsurancePricingEngine
{
    /**
     * @return array<int, array{plan: InsurancePlan, premium_cents: int, currency: string}>
     */
    public function getAvailablePlansForBooking(int $bookingId): array
    {
        $bookingMeta = $this->resolveBookingMeta($bookingId);
        if (! $bookingMeta) {
            return [];
        }

        $plans = InsurancePlan::query()
            ->active()
            ->get()
            ->filter(fn (InsurancePlan $p) => $p->appliesToTrade($bookingMeta['trade_code']) && $p->isWithinValidity());

        $out = [];
        foreach ($plans as $plan) {
            $premium = $this->computePremium($plan, $bookingMeta['amount_cents']);
            $out[] = [
                'plan' => $plan,
                'premium_cents' => $premium,
                'currency' => $plan->currency,
            ];
        }

        return $out;
    }

    public function computePremium(InsurancePlan $plan, int $bookingAmountCents): int
    {
        $percentPart = (int) round(($plan->premium_percent / 100) * $bookingAmountCents);
        $premium = (int) $plan->premium_base_cents + $percentPart;

        if ($premium < (int) $plan->min_premium_cents) {
            $premium = (int) $plan->min_premium_cents;
        }
        if ($plan->max_premium_cents !== null && $premium > (int) $plan->max_premium_cents) {
            $premium = (int) $plan->max_premium_cents;
        }

        return max(0, $premium);
    }

    /**
     * @return array{trade_code: ?string, amount_cents: int, currency: string, client_id: ?int, provider_user_id: ?int}|null
     */
    public function resolveBookingMeta(int $bookingId): ?array
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }
        $row = DB::table('bookings')->where('id', $bookingId)->first();
        if (! $row) {
            return null;
        }

        $amountCents = 0;
        foreach (['payment_amount_cents', 'final_price', 'estimated_price'] as $col) {
            if (isset($row->{$col}) && $row->{$col} !== null) {
                $val = (float) $row->{$col};
                $amountCents = $col === 'payment_amount_cents' ? (int) $val : (int) round($val * 100);
                if ($amountCents > 0) {
                    break;
                }
            }
        }

        $tradeCode = null;
        if (Schema::hasColumn('bookings', 'service_catalog_id') && isset($row->service_catalog_id)) {
            // Lookup service_catalog → trade
            if (Schema::hasTable('service_catalogs') && Schema::hasColumn('service_catalogs', 'trade_id')) {
                $service = DB::table('service_catalogs')->where('id', $row->service_catalog_id)->first();
                if ($service && Schema::hasTable('trades')) {
                    $trade = DB::table('trades')->where('id', $service->trade_id ?? 0)->first();
                    $tradeCode = $trade->code ?? null;
                }
            }
        }

        $clientId = $row->client_id ?? $row->customer_user_id ?? null;

        /*
         * L'ASSURANCE COUVRE CELUI QUI EST SUR PLACE — c'est lui qui casse le vase.
         *
         * Cette méthode lit la table en direct, sans modèle : `Booking::intervenantId()` ne s'y
         * applique pas, mais l'ordre de priorité doit être LE MÊME, sinon la police nomme un
         * prestataire pendant que le portefeuille en paie un autre.
         */
        $providerUserId = Schema::hasTable('missions')
            ? DB::table('missions')->where('booking_id', $bookingId)
                ->orderByDesc('id')->value('lead_provider_user_id')
            : null;
        $providerUserId ??= $row->employe_id ?? $row->assigned_provider_user_id ?? null;

        return [
            'trade_code' => $tradeCode,
            'amount_cents' => $amountCents,
            'currency' => $row->currency ?? config('insurance.default_currency'),
            'client_id' => $clientId ? (int) $clientId : null,
            'provider_user_id' => $providerUserId ? (int) $providerUserId : null,
        ];
    }
}
