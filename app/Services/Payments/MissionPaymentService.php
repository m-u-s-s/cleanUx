<?php

namespace App\Services\Payments;

use App\Models\Booking;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MissionPaymentService
{
    /**
     * LES FRAIS D'ANNULATION ONT ÉTÉ ENCAISSÉS, LA PRESTATION N'A PAS EU LIEU.
     *
     * Un état à part, et non `captured`, parce que toute la chaîne d'aval lit `captured` comme
     * « la mission a été payée » : le webhook crédite alors au prestataire la part de la commande
     * entière, et la comptabilité enregistre une prestation. Ici, seuls des frais ont été pris.
     *
     * Stripe, lui, dit « succeeded » — c'est le même objet. C'est donc à nous de tenir la
     * distinction, et `syncPaymentIntent()` refuse d'écraser cette valeur pour cette raison.
     */
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

    /**
     * ENCAISSER LES FRAIS D'ANNULATION SUR UNE EMPREINTE — un seul appel, deux effets.
     *
     * Une capture PARTIELLE prend les frais et fait libérer le solde par Stripe dans la foulée. Les
     * deux autres options ont été écartées à la décision du 2026-08-12 : annuler l'empreinte puis
     * facturer les frais à part crée un second flux à réconcilier, et ne rien facturer renonce à
     * des frais réels.
     *
     * L'ANCIEN CHEMIN NE FACTURAIT RIEN. `CancelBookingService` routait l'empreinte vers
     * `refundMissionPayment()`, qui refuse tout ce qui n'est pas `captured` — l'exception était
     * attrapée et journalisée, l'annulation répondait « ok », les frais n'étaient jamais encaissés
     * et l'empreinte tenait jusqu'à expiration, environ sept jours.
     *
     * ── LE STATUT RENDU EST DISTINCT DE `captured`, ET C'EST VITAL ────────────────────────────
     *
     * `handlePaymentIntentSucceeded` crédite le prestataire dès qu'il lit `captured`, et il crédite
     * `provider_amount_cents` — la part de la COMMANDE ENTIÈRE. Sur 120 € annulés à 24 € de frais,
     * il toucherait 96 € pour une prestation jamais faite. D'où `fee_captured`, que
     * `syncPaymentIntent()` refuse d'écraser bien que Stripe dise « succeeded ».
     *
     * ── L'ACOMPTE DÉJÀ DÉBITÉ SE DÉDUIT ───────────────────────────────────────────────────────
     *
     * Sur un plan à acompte, les frais se calculent sur le TOTAL mais ne peuvent se capturer que
     * sur l'intention du SOLDE. Sans déduction, le client paie l'acompte PLUS la totalité des
     * frais ; et au-delà d'environ 70 % de frais, la capture dépasserait l'autorisation et Stripe
     * la rejetterait.
     */
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

        /*
         * RIEN À PRENDRE : ON LIBÈRE, ON NE CAPTURE PAS ZÉRO.
         *
         * Stripe refuse une capture nulle. C'est le cas d'une annulation gratuite, et celui d'un
         * acompte qui couvre déjà les frais. L'empreinte doit alors être rendue tout de suite
         * plutôt que de tenir une semaine sur la carte de quelqu'un qui ne doit rien.
         */
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
     * On garde ce qui a été réclamé, ce qui a été pris, et ce que l'acompte couvrait déjà. Sans ces
     * trois nombres, expliquer un débit de 12 € sur une commande de 120 € demanderait de refaire le
     * calcul avec la grille du jour — qui aura changé.
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
