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

    /**
     * FRAIS D'ANNULATION ENCAISSÉS — le seul flux d'argent qui n'atteignait aucun livre.
     *
     * Le taux de TVA se résout ici et non dans le service de passage : c'est ce fichier qui sait
     * déjà lire le pays d'une réservation, et le dupliquer plus bas ferait diverger les deux
     * copies. Le réglage dédié l'emporte quand il existe — y compris à zéro, qui est une position
     * fiscale voulue (« hors champ ») et non une absence de valeur, d'où le test sur `null` plutôt
     * que sur la vacuité.
     */
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

    /**
     * L'INTERRUPTEUR APPARTIENT AU COMPTABLE, PAS AU FICHIER `.env`.
     *
     * Il reste coupé par défaut — la compta valide les écritures avant qu'elles ne s'accumulent —
     * mais le lever ne demande plus un accès au serveur et un redéploiement. Le repli reste la
     * configuration, si bien qu'une base neuve se comporte exactement comme avant.
     */
    protected static function glEnabled(): bool
    {
        return app(ReglagesComptables::class)->postageAutomatique()
            && Schema::hasTable('accounting_entries');
    }

    protected static function shouldPost(Booking $booking): bool
    {
        return self::glEnabled();
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

    /**
     * LA TVA COMPTABLE LIT DÉSORMAIS LA MÊME AUTORITÉ QUE LE RESTE DE LA PLATEFORME.
     *
     * Il y en avait TROIS, et le comptable n'en tenait aucune :
     *
     *   `CountryBillingProfile.default_tax_rate`     lue par le calcul de prix
     *   `CountryOperationalSetting.default_tax_rate` éditable depuis les opérations internationales
     *   `config('accounting_v2.vat_rates')`          lue ICI, et seulement ici
     *
     * Un administrateur qui corrigeait le taux d'un pays dans l'écran prévu pour cela changeait le
     * prix facturé au client SANS changer la TVA portée au journal. Les deux nombres divergeaient
     * en silence, et c'est le journal qui sert à déclarer.
     *
     * L'ORDRE VA DU PLUS PRÉCIS AU PLUS GÉNÉRAL, et chaque cran a sa raison :
     *
     *   1. le taux porté par la réservation elle-même — figé au moment de la vente, il fait foi ;
     *   2. le profil de facturation puis le réglage du pays — l'autorité que le reste de la
     *      plateforme consulte, celle que l'administration édite ;
     *   3. la table de la configuration comptable, conservée en REPLI pour ne rien casser là où
     *      aucune donnée pays n'existe encore : `FR` y vaut 20 %, et basculer d'autorité sans ce
     *      cran l'aurait fait passer à 21 % sans que personne le demande.
     *
     * Zéro reste une valeur : un pays exonéré doit rendre `0.0`, pas retomber au cran suivant.
     * D'où les tests sur `null` plutôt que sur la vacuité.
     */
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

    /**
     * Le taux RÉELLEMENT renseigné pour le pays de la réservation, ou `null` si aucun ne l'est.
     *
     * On ne passe pas par `CountryMarketResolver::effectiveTaxRate()` bien qu'il lise les mêmes
     * sources : il retombe sur 21 % quand rien n'est posé, et cette valeur de politesse est
     * indiscernable d'un vrai 21 %. Le repli sur la table comptable ne serait alors JAMAIS
     * emprunté — la France passerait de 20 à 21 % sans que rien ne le signale.
     *
     * La résolution du pays, elle, est bien celle du reste de la plateforme : elle part de la
     * POSITION — site, code postal, zone de service — et non d'une préférence de compte.
     */
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
