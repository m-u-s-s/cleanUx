<?php

namespace App\Support\Accounting;

use App\Models\Booking;
use App\Services\AccountingV2\Posting\BookingPostingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Helper safe-fail pour brancher AccountingV2 sur les events Booking sans
 * casser le flow business si le module est désactivé ou si la table n'existe pas.
 *
 * Désactivé par défaut via config('accounting_v2.auto_post_enabled')=false.
 * Activer en prod uniquement après validation manuelle des écritures par compta.
 */
class BookingAutoPoster
{
    public static function postSale(Booking $booking): void
    {
        if (! self::shouldPost($booking)) {
            return;
        }
        try {
            $ttcCents = self::extractTtcCents($booking);
            if ($ttcCents <= 0) {
                return;
            }
            $vatRate = self::resolveVatRate($booking);
            app(BookingPostingService::class)->postBookingSale($booking, $ttcCents, $vatRate);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] sale failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    public static function postPayment(Booking $booking, int $stripeFeeCents = 0): void
    {
        if (! self::shouldPost($booking)) {
            return;
        }
        try {
            $ttcCents = self::extractTtcCents($booking);
            if ($ttcCents <= 0) {
                return;
            }
            app(BookingPostingService::class)->postBookingPayment($booking, $ttcCents, $stripeFeeCents);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] payment failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    public static function postRefund(Booking $booking, int $refundCents): void
    {
        if (! self::shouldPost($booking)) {
            return;
        }
        try {
            if ($refundCents <= 0) {
                return;
            }
            app(BookingPostingService::class)->postRefund($booking, $refundCents);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] refund failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function shouldPost(Booking $booking): bool
    {
        if (! (bool) config('accounting_v2.auto_post_enabled', false)) {
            return false;
        }
        if (! Schema::hasTable('accounting_entries')) {
            return false;
        }
        return true;
    }

    /**
     * Cherche le montant TTC dans plusieurs colonnes possibles (compatible legacy schemas).
     */
    protected static function extractTtcCents(Booking $booking): int
    {
        $candidates = [
            'total_amount_cents', 'payment_amount_cents',
            'final_price_cents', 'amount_cents',
        ];
        foreach ($candidates as $col) {
            $v = $booking->{$col} ?? null;
            if (is_numeric($v) && (int) $v > 0) {
                return (int) $v;
            }
        }
        // Convert from float price if no cents column found
        $floatCandidates = ['final_price', 'estimated_price', 'devis_estime', 'total_amount'];
        foreach ($floatCandidates as $col) {
            $v = $booking->{$col} ?? null;
            if (is_numeric($v) && (float) $v > 0) {
                return (int) round(((float) $v) * 100);
            }
        }
        return 0;
    }

    protected static function resolveVatRate(Booking $booking): float
    {
        // Tentatives multiples : column dédiée, metadata, ou default config par pays
        if (isset($booking->vat_rate) && is_numeric($booking->vat_rate)) {
            return (float) $booking->vat_rate;
        }
        $country = $booking->country_code
            ?? ($booking->metadata['country_code'] ?? null)
            ?? config('accounting_v2.default_country_code', 'BE');
        $rates = (array) config('accounting_v2.vat_rates', []);
        return (float) ($rates[$country] ?? 21.0);
    }
}
