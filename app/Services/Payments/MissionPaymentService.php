<?php

namespace App\Services\Payments;

use App\Models\Booking;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MissionPaymentService
{
    /** LES FRAIS D'ANNULATION ONT ÉTÉ ENCAISSÉS, LA PRESTATION N'A PAS EU LIEU. */
    public const STATUT_FRAIS_CAPTURES = 'fee_captured';

    public function __construct(private ?CommissionService $commissionService = null)
    {
        Stripe::setApiKey(config('cashier.secret'));
        $this->commissionService ??= app(CommissionService::class);
    }

    public function authorize(Booking $rendezVous, string $paymentMethodId): PaymentIntent
    {
        $rendezVous->loadMissing(['client', 'employe']);

        $employee = $rendezVous->employe;

        if (! $employee || ! $employee->canReceiveStripeConnectPayments()) {
            throw new RuntimeException('Le prestataire ne peut pas encore recevoir de paiements Stripe Connect.');
        }

        if (! $rendezVous->client?->stripe_id) {
            $rendezVous->client?->createAsStripeCustomer();
            $rendezVous->refresh()->loadMissing('client');
        }

        // Source de vérité UNIQUE pour le split commission/payout : le même calcul
        // alimente le ledger/wallet à la complétion. Évite la divergence
        // Stripe-charge ↔ compta (ex-bug : calcul env dupliqué ici).
        $commission = $this->commissionService->calculateForBooking($rendezVous);

        $amount = $commission['total_cents'];
        $platformFee = $commission['platform_fee_cents'];
        $providerAmount = $commission['provider_payout_cents'];

        $intent = PaymentIntent::create([
            'amount' => $amount,
            // L'instantane de prix d'abord -- c'est lui qui a fixe le montant --, puis la
            // devise de la reservation, puis celle de la plateforme. Jamais un « eur » ecrit ici.
            'currency' => strtolower(
                $rendezVous->pricing_snapshot['currency']
                    ?? $rendezVous->currency
                    ?? (string) config('fx.base_currency', 'EUR')
            ),
            'customer' => $rendezVous->client->stripe_id,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'capture_method' => 'manual',
            'application_fee_amount' => $platformFee,
            'transfer_data' => [
                'destination' => $employee->stripe_connect_account_id,
            ],
            'metadata' => [
                'rendez_vous_id' => $rendezVous->id,
                'booking_reference' => $rendezVous->booking_reference,
                'client_id' => $rendezVous->client_id,
                'employee_id' => $employee->id,
                'platform_fee_cents' => $platformFee,
                'provider_amount_cents' => $providerAmount,
            ],
        ]);

        // `stripe_connect_account_id` N'EXISTE PAS sur `bookings` : cette écriture était perdue en
        // silence depuis toujours. Le compte Connect fait autorité sur `users`, où il est lu.
        $rendezVous->forceFill([
            'stripe_payment_intent_id' => $intent->id,
            'payment_amount_cents' => $amount,
            'platform_fee_cents' => $platformFee,
            'provider_amount_cents' => $providerAmount,
            'payment_status' => 'authorized',
            'payment_authorized_at' => now(),
        ])->save();

        return $intent;
    }

    /**
     * LE MÊME PAIEMENT, MAIS CONFIRMÉ PAR L'APPLICATION MOBILE.
     *
     * @throws RuntimeException si aucun prestataire ne peut recevoir le virement
     */
    public function autoriserPourConfirmationClient(Booking $rendezVous): PaymentIntent
    {
        $rendezVous->loadMissing(['client', 'employe']);

        $employee = $rendezVous->employe;

        if (! $employee || ! $employee->canReceiveStripeConnectPayments()) {
            throw new RuntimeException(
                'Le paiement s’ouvre une fois le prestataire assigné : sans compte destinataire, '.
                'la plateforme encaisserait la totalité sans pouvoir la reverser.'
            );
        }

        if (! $rendezVous->client?->stripe_id) {
            $rendezVous->client?->createAsStripeCustomer();
            $rendezVous->refresh()->loadMissing('client');
        }

        // DEUX IDENTIFIANTS SANS LESQUELS L'APPEL N'A PAS DE SENS, vérifiés explicitement.
        $compteDestinataire = $employee->stripe_connect_account_id;
        $clientStripeId = $rendezVous->client?->stripe_id;

        if (! is_string($compteDestinataire) || $compteDestinataire === '') {
            throw new RuntimeException('Le prestataire n’a pas de compte Stripe Connect utilisable.');
        }

        if (! is_string($clientStripeId) || $clientStripeId === '') {
            throw new RuntimeException('Le client n’a pas de compte Stripe : impossible d’ouvrir un paiement.');
        }

        // Même source de vérité que le web : le split alimente ensuite le ledger à la complétion.
        $commission = $this->commissionService->calculateForBooking($rendezVous);

        $amount = $commission['total_cents'];
        $platformFee = $commission['platform_fee_cents'];
        $providerAmount = $commission['provider_payout_cents'];

        $intent = PaymentIntent::create([
            'amount' => $amount,
            // L'instantane de prix d'abord -- c'est lui qui a fixe le montant --, puis la
            // devise de la reservation, puis celle de la plateforme. Jamais un « eur » ecrit ici.
            'currency' => strtolower(
                $rendezVous->pricing_snapshot['currency']
                    ?? $rendezVous->currency
                    ?? (string) config('fx.base_currency', 'EUR')
            ),
            'customer' => $clientStripeId,
            // Capture MANUELLE : on autorise à la commande, on encaisse à la fin. Une capture
            // automatique prendrait l'argent avant que le travail soit fait, et rendrait toute
            // annulation plus coûteuse qu'un simple relâchement d'empreinte.
            'capture_method' => 'manual',
            'application_fee_amount' => $platformFee,
            'transfer_data' => [
                'destination' => $compteDestinataire,
            ],
            'metadata' => [
                // `booking_id` EST LE FILET DU WEBHOOK. `StripeWebhookHandlers` cherche d'abord par
                // `stripe_payment_intent_id` ; si cette colonne n'a pas encore été écrite — course
                // entre la confirmation sur l'appareil et notre écriture — c'est cette métadonnée
                // qui permet de retrouver la réservation.
                // Stripe stocke les métadonnées en CHAÎNES : on convertit ici plutôt que de
                // laisser l'API le faire, sinon `null` deviendrait la chaîne vide et l'on croirait
                // à une référence absente là où il n'y a qu'une conversion implicite.
                'booking_id' => (string) $rendezVous->id,
                'rendez_vous_id' => (string) $rendezVous->id,
                'booking_reference' => (string) ($rendezVous->booking_reference ?? ''),
                'client_id' => (string) ($rendezVous->client_id ?? ''),
                'employee_id' => (string) $employee->id,
                'platform_fee_cents' => (string) $platformFee,
                'provider_amount_cents' => (string) $providerAmount,
            ],
        ]);

        $rendezVous->forceFill([
            'stripe_payment_intent_id' => $intent->id,
            'payment_amount_cents' => $amount,
            'platform_fee_cents' => $platformFee,
            'provider_amount_cents' => $providerAmount,
            // PAS `authorized` ICI : l'autorisation n'existera qu'une fois la feuille de paiement
            // confirmée sur l'appareil. C'est le webhook qui fait passer le statut. Écrire
            // « autorisé » dès la création ferait croire à un paiement que le client peut encore
            // abandonner d'un retour arrière.
            'payment_status' => 'pending',
        ])->save();

        return $intent;
    }

    public function capture(Booking $rendezVous): ?PaymentIntent
    {
        if (! $rendezVous->stripe_payment_intent_id) {
            return null;
        }

        if ($rendezVous->payment_status !== 'authorized') {
            return null;
        }

        $intent = PaymentIntent::retrieve($rendezVous->stripe_payment_intent_id);
        $intent->capture();

        $rendezVous->forceFill([
            'payment_status' => 'captured',
            'payment_captured_at' => now(),
        ])->save();

        return $intent;
    }

    /** ENCAISSER LES FRAIS D'ANNULATION SUR UNE EMPREINTE — un seul appel, deux effets. */
    public function capturerLesFraisDAnnulation(Booking $rendezVous, int $fraisCents): ?PaymentIntent
    {
        if (! $rendezVous->stripe_payment_intent_id || $rendezVous->payment_status !== 'authorized') {
            return null;
        }

        $intent = PaymentIntent::retrieve($rendezVous->stripe_payment_intent_id);

        // Ce que Stripe accepte encore de prendre sur cette empreinte.
        $capturable = (int) ($intent->amount_capturable ?? $intent->amount ?? 0);

        $dejaPaye = (int) ($rendezVous->deposit_amount_cents ?? 0);
        $aPrendre = max(0, $fraisCents - $dejaPaye);
        $aPrendre = min($aPrendre, $capturable);

        // RIEN À PRENDRE : ON LIBÈRE, ON NE CAPTURE PAS ZÉRO. Stripe refuse une capture nulle.
        if ($aPrendre <= 0) {
            $intent->cancel();

            $rendezVous->forceFill([
                'payment_status' => 'cancelled',
                'metadata' => $this->noterLesFrais($rendezVous, 0, $fraisCents, $dejaPaye),
            ])->save();

            return $intent;
        }

        $intent->capture(['amount_to_capture' => $aPrendre]);

        $rendezVous->forceFill([
            'payment_status' => self::STATUT_FRAIS_CAPTURES,
            'payment_captured_at' => now(),
            'metadata' => $this->noterLesFrais($rendezVous, $aPrendre, $fraisCents, $dejaPaye),
        ])->save();

        return $intent;
    }

    /**
     * LA TRACE DU CALCUL, parce qu'un client contestera.
     *
     * @return array<string, mixed>
     */
    private function noterLesFrais(Booking $rendezVous, int $pris, int $dus, int $acompte): array
    {
        $journal = $rendezVous->metadata ?? [];

        $journal['frais_annulation'] = [
            'a' => now()->toIso8601String(),
            'dus_cents' => $dus,
            'acompte_deja_debite_cents' => $acompte,
            'captures_cents' => $pris,
        ];

        return $journal;
    }

    public function markFailed(Booking $rendezVous): void
    {
        $rendezVous->forceFill([
            'payment_status' => 'failed',
            'payment_failed_at' => now(),
        ])->save();
    }
}
