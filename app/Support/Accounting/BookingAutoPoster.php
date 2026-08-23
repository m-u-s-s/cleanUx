<?php

namespace App\Support\Accounting;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\BookingTip;
use App\Services\AccountingV2\Posting\BookingPostingService;
use App\Services\AccountingV2\ReglagesComptables;
use App\Services\International\CountryMarketResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Helper safe-fail pour brancher AccountingV2 sur les events Booking sans casser le flow business si le module est désactivé ou si la table n'existe pas. */
class BookingAutoPoster
{
    public static function postSale(Booking $booking): void
    {
        if (! self::shouldPost($booking)) {
            return;
        }
        try {
            // Audit MEDIUM — modèle agent : la commission est le produit, la part
            // prestataire une dette. Sinon (principal) : TTC complet en ventes.
            if (app(ReglagesComptables::class)->modeleDeRevenu() === 'agent') {
                app(BookingPostingService::class)->postMarketplaceSettlement($booking);

                return;
            }

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

    /** FRAIS D'ANNULATION ENCAISSÉS — le seul flux d'argent qui n'atteignait aucun livre. */
    public static function postCancellationFee(
        Booking $booking,
        int $feeCents,
        int $stripeFeeCents = 0,
        int $providerShareCents = 0,
    ): void {
        if (! self::shouldPost($booking)) {
            return;
        }
        try {
            if ($feeCents <= 0) {
                return;
            }

            $pose = app(ReglagesComptables::class)->tvaDesFraisDAnnulation();
            $taux = $pose ?? self::resolveVatRate($booking);

            app(BookingPostingService::class)
                ->postCancellationFee($booking, $feeCents, $stripeFeeCents, $taux, $providerShareCents);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] cancellation fee failed', [
                'booking_id' => $booking->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    public static function postTip(BookingTip $tip): void
    {
        if (! self::glEnabled()) {
            return;
        }
        try {
            app(BookingPostingService::class)->postTipSettlement($tip);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] tip failed', [
                'tip_id' => $tip->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    public static function postInsurance(BookingInsurance $insurance): void
    {
        if (! self::glEnabled()) {
            return;
        }
        try {
            app(BookingPostingService::class)->postInsuranceSettlement($insurance);
        } catch (\Throwable $e) {
            Log::warning('[accounting_auto_post] insurance failed', [
                'insurance_id' => $insurance->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** L'INTERRUPTEUR APPARTIENT AU COMPTABLE, PAS AU FICHIER `.env`. */
    protected static function glEnabled(): bool
    {
        return app(ReglagesComptables::class)->postageAutomatique()
            && Schema::hasTable('accounting_entries');
    }

    protected static function shouldPost(Booking $booking): bool
    {
        return self::glEnabled();
    }

    /** Cherche le montant TTC dans plusieurs colonnes possibles (compatible legacy schemas). */
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

    /** LA TVA COMPTABLE LIT DÉSORMAIS LA MÊME AUTORITÉ QUE LE RESTE DE LA PLATEFORME. */
    protected static function resolveVatRate(Booking $booking): float
    {
        if (isset($booking->vat_rate) && is_numeric($booking->vat_rate)) {
            return (float) $booking->vat_rate;
        }

        $pose = self::tauxPoseParLePays($booking);

        if ($pose !== null) {
            return $pose;
        }

        $country = $booking->country_code
            ?? ($booking->metadata['country_code'] ?? null)
            ?? config('accounting_v2.default_country_code', 'BE');
        $rates = (array) config('accounting_v2.vat_rates', []);

        return (float) ($rates[$country] ?? 21.0);
    }

    /** Le taux RÉELLEMENT renseigné pour le pays de la réservation, ou `null` si aucun ne l'est. */
    private static function tauxPoseParLePays(Booking $booking): ?float
    {
        try {
            $contexte = app(CountryMarketResolver::class)->resolveForRendezVous($booking);
        } catch (\Throwable $e) {
            return null;
        }

        foreach (['billing_profile', 'operational_setting'] as $source) {
            $valeur = data_get($contexte[$source] ?? null, 'default_tax_rate');

            if ($valeur !== null && $valeur !== '') {
                return (float) $valeur;
            }
        }

        return null;
    }
}
