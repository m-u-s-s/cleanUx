<?php

namespace App\Services\Payments;

use App\Models\Booking;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MissionPaymentService
{
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
            'currency' => strtolower($rendezVous->pricing_snapshot['currency'] ?? 'eur'),
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
     * POURQUOI UNE SECONDE MÉTHODE PLUTÔT QU'UN PARAMÈTRE. `authorize()` ci-dessus reçoit un moyen
     * de paiement et confirme côté serveur : c'est le parcours web. Le mobile, lui, a besoin d'un
     * `client_secret` que la feuille de paiement Stripe confirme sur l'appareil — on ne peut donc
     * ni passer `payment_method`, ni `confirm`. Tout le reste doit être IDENTIQUE, et l'est :
     * même calcul de commission, même charge à destination, même capture manuelle, mêmes colonnes
     * écrites sur la réservation.
     *
     * CE QUE CETTE MÉTHODE RÉPARE. Le contrôleur mobile fabriquait son intent à la main :
     * ni `transfer_data.destination`, ni `application_fee_amount`, `capture_method` automatique, et
     * l'identifiant de l'intent n'était JAMAIS écrit sur la réservation. Conséquences cumulées :
     * la plateforme encaissait 100 % de la course, le prestataire n'était jamais payé, l'argent
     * était pris à la commande au lieu de l'être à la fin, et le webhook ne retrouvait pas la
     * réservation — donc aucun statut de paiement ne remontait.
     *
     * POURQUOI ON REFUSE SANS PRESTATAIRE. Une charge à destination exige un compte destinataire.
     * Tant que personne n'est assigné, il n'y en a pas : le seul intent possible encaisserait tout
     * sur la plateforme, c'est-à-dire exactement le défaut qu'on ferme. Facturer d'abord et promettre
     * de reverser ensuite serait un second système de paiement, avec sa propre réconciliation — un
     * choix de modèle, pas une correction. On refuse donc, explicitement, plutôt que d'encaisser un
     * argent qu'on ne saurait pas router.
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

        /*
         * DEUX IDENTIFIANTS SANS LESQUELS L'APPEL N'A PAS DE SENS, vérifiés explicitement.
         *
         * `canReceiveStripeConnectPayments()` ci-dessus implique le compte Connect, mais rien dans
         * les types ne le dit : un refus muet ici enverrait `destination => null` à Stripe, qui
         * créerait une charge SANS destinataire — soit exactement le défaut qu'on ferme, mais plus
         * difficile à voir puisque l'intent, lui, réussirait.
         */
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
            'currency' => strtolower($rendezVous->pricing_snapshot['currency'] ?? 'eur'),
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

    public function markFailed(Booking $rendezVous): void
    {
        $rendezVous->forceFill([
            'payment_status' => 'failed',
            'payment_failed_at' => now(),
        ])->save();
    }
}
