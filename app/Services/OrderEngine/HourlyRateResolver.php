<?php

namespace App\Services\OrderEngine;

use App\Models\Booking;
use App\Models\Trade;
use App\Models\TradeZonePricing;

/** COMBIEN VAUT UNE HEURE — une seule réponse, un seul endroit. */
class HourlyRateResolver
{
    /** Le tarif horaire du catalogue, en centimes. */
    public function tarifCatalogue(Trade $trade, ?int $serviceZoneId = null): ?int
    {
        if (! $trade->hourly_billing) {
            return null;
        }

        if ($serviceZoneId !== null) {
            $surcharge = TradeZonePricing::query()
                ->where('trade_id', $trade->id)
                ->where('service_zone_id', $serviceZoneId)
                ->value('price_per_hour_cents');

            // `!== null` et non `filled()` : une zone peut délibérément poser 0 — « une heure est offerte ici ».
            if ($surcharge !== null) {
                return (int) $surcharge;
            }
        }

        $reference = $trade->default_hourly_rate;

        if ($reference === null || (float) $reference <= 0.0) {
            return null;
        }

        return (int) round((float) $reference * 100);
    }

    /** Le tarif horaire RÉELLEMENT payé sur cette réservation, en centimes — multiplicateurs inclus. */
    public function tarifEffectifDeLaReservation(Booking $booking): ?int
    {
        if (! $this->seFactureALHeure($booking)) {
            return null;
        }

        $minutes = (int) ($booking->estimated_duration_minutes ?? $booking->estimated_duration_minutes ?? 0);

        if ($minutes <= 0) {
            return null;
        }

        $montantCents = $this->montantFactureCents($booking);

        if ($montantCents <= 0) {
            return null;
        }

        return (int) round($montantCents / ($minutes / 60));
    }

    /** Le montant sur lequel raisonner, en centimes. */
    public function montantFactureCents(Booking $booking): int
    {
        $autorise = (int) ($booking->payment_amount_cents ?? 0);

        if ($autorise > 0) {
            return $autorise;
        }

        $devis = $booking->devis_estime ?? $booking->estimated_price ?? 0;

        return (int) round((float) $devis * 100);
    }

    /** Cette réservation est-elle facturée au temps passé ? */
    public function seFactureALHeure(Booking $booking): bool
    {
        $tradeId = $booking->resolveTradeId();

        if ($tradeId === null) {
            return false;
        }

        return (bool) Trade::query()->whereKey($tradeId)->value('hourly_billing');
    }
}
