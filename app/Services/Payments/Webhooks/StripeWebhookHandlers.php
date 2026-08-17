<?php

namespace App\Services\Payments\Webhooks;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Notifications\Payments\PaymentFailedNotification;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\StripeConnectService;
use App\Services\Tips\TipService;
use App\Support\Accounting\BookingAutoPoster;
use App\Support\Webhooks\BusinessEventEmitter;
use Illuminate\Support\Facades\Log;

/**
 * Implémentations des handlers Stripe par type d'événement.
 *
 * Extraits de StripeWebhookEventProcessor pour limiter la taille du god-object.
 * Behavior identique — aucune logique métier modifiée.
 */
class StripeWebhookHandlers
{
    public function __construct(
        protected StripeConnectService $connectService,
        protected StripeConnectPaymentService $paymentService,
        protected ProviderWalletService $walletService,
    ) {}

    /** Le volet « solde » d'un plan à acompte — et le seul intent des réservations ordinaires. */
    private const VOLET_SOLDE = 'balance';

    /** Le volet « acompte » : déjà débité à la commande, sur sa propre intention Stripe. */
    private const VOLET_ACOMPTE = 'deposit';

    /**
     * QUELLE RÉSERVATION, ET QUEL VOLET DE SON PAIEMENT ?
     *
     * Une réservation à acompte porte DEUX intentions Stripe : `deposit_payment_intent_id`, débitée
     * à la commande, et `stripe_payment_intent_id`, le solde bloqué jusqu'à la fin. Les webhooks ne
     * cherchaient que la seconde : tout événement portant sur l'acompte — encaissement,
     * remboursement, échec — ne trouvait aucune réservation et sortait en `ignored`. La colonne
     * était écrite par le planificateur de paiement et relue par PERSONNE.
     *
     * LE VOLET EST RENDU AVEC LA RÉSERVATION, et ce n'est pas un confort. `payment_status` décrit
     * le SOLDE, pas l'acompte : le rattacher sans le dire ferait écrire « remboursé » sur une
     * réservation dont le solde n'est qu'autorisé, et ce solde deviendrait définitivement non
     * capturable. Chaque appelant doit savoir de quel volet on lui parle.
     *
     * @return array{0: Booking|null, 1: string}
     */
    private function reservationPourIntent(?string $piId): array
    {
        if (! $piId) {
            return [null, self::VOLET_SOLDE];
        }

        $booking = Booking::query()->where('stripe_payment_intent_id', $piId)->first();

        if ($booking) {
            return [$booking, self::VOLET_SOLDE];
        }

        $booking = Booking::query()->where('deposit_payment_intent_id', $piId)->first();

        return [$booking, $booking ? self::VOLET_ACOMPTE : self::VOLET_SOLDE];
    }

    public function handleAccountUpdated(array $account): array
    {
        $accountId = $account['id'] ?? null;
        if (! $accountId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $user = User::query()->where('stripe_connect_account_id', $accountId)->first();
        if (! $user) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'no_user_for_account']];
        }

        if (method_exists($this->connectService, 'syncAccountStatus')) {
            $this->connectService->syncAccountStatus($user);
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['user_id' => $user->id]];
    }

    public function handlePayoutPaid(array $payout): array
    {
        $stripePayoutId = $payout['id'] ?? null;
        if (! $stripePayoutId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel = ProviderPayout::query()
            ->where('provider_payout_id', $stripePayoutId)
            ->first();

        if (! $payoutModel) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'no_local_payout']];
        }

        if ($payoutModel->status === ProviderPayout::STATUS_PAID) {
            return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['already' => true]];
        }

        $payoutModel->markAsPaid($stripePayoutId);
        $this->walletService->markPayoutCleared($payoutModel, $stripePayoutId);

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['payout_id' => $payoutModel->id]];
    }

    public function handlePayoutFailed(array $payout): array
    {
        $stripePayoutId = $payout['id'] ?? null;
        if (! $stripePayoutId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel = ProviderPayout::query()
            ->where('provider_payout_id', $stripePayoutId)
            ->first();

        if (! $payoutModel) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel->markAsFailed([
            'failure_code' => $payout['failure_code'] ?? null,
            'failure_message' => $payout['failure_message'] ?? null,
        ]);

        $this->walletService->reversePayout($payoutModel, $payout['failure_message'] ?? 'stripe_payout_failed');

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['payout_id' => $payoutModel->id]];
    }

    public function handleChargeRefunded(array $charge): array
    {
        $pi = $charge['payment_intent'] ?? null;
        if (! $pi) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        [$booking, $volet] = $this->reservationPourIntent($pi);

        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $isTotal = (int) ($charge['amount_refunded'] ?? 0) >= (int) ($charge['amount'] ?? 0);
        $refundedAmountCents = (int) ($charge['amount_refunded'] ?? 0);

        $alreadyHandled = $booking->payment_status === ($isTotal ? 'refunded' : 'partially_refunded');

        /*
         * `payment_status` DECRIT LE SOLDE, JAMAIS L'ACOMPTE.
         *
         * Rembourser un acompte et ecrire « partiellement rembourse » sur la reservation rendrait
         * le solde -- qui n'est qu'AUTORISE -- definitivement non capturable : la capture refuse
         * tout statut autre que `authorized`. Le remboursement de l'acompte est reel et se
         * comptabilise ; il ne dit simplement rien de l'autre volet.
         */
        /*
         * LE SOLDE LIBÉRÉ N'EST PAS UN REMBOURSEMENT.
         *
         * Après une capture partielle des frais d'annulation, Stripe rend le reste de l'empreinte
         * — et il le rend sous forme de REMBOURSEMENT sur la charge, objet `re_…` compris. Rien
         * dans la charge ne permet de le distinguer d'un vrai remboursement client.
         *
         * Écrire « partiellement remboursé » par-dessus `fee_captured` effacerait la seule trace
         * disant que ces euros sont des frais et non une prestation. La reprise sur le portefeuille
         * est déjà neutralisée en amont — sans gain enregistré, il n'y a rien à reprendre — mais le
         * statut, lui, se défend ici.
         */
        $fraisEncaisses = $booking->payment_status === MissionPaymentService::STATUT_FRAIS_CAPTURES;

        if (! $alreadyHandled && ! $fraisEncaisses && $volet === self::VOLET_SOLDE) {
            $booking->forceFill([
                'payment_status' => $isTotal ? 'refunded' : 'partially_refunded',
                'payment_refunded_at' => now(),
            ])->save();
        }

        // Clawback strategy: iterate refunds.data so each distinct Stripe Refund
        // (re_xxx) gets its own idempotent clawback entry.  This unifies the
        // service path (refundMissionPayment passes re_xxx) and the webhook path:
        // because both keys on the Refund id, a service-then-webhook flow dedupes
        // to a single row.  Distinct partial refunds each have their own re_xxx so
        // they still produce separate clawbacks.
        //
        // Proportional formula per refund (same as StripeConnectPaymentService):
        //   clawbackCents = round(refundCents × providerCents / max(1, totalCents))
        $totalCents = max(1, (int) ($charge['amount'] ?? $booking->payment_amount_cents ?? 0));
        $providerCents = (int) ($booking->provider_amount_cents ?? $totalCents);

        $perRefundData = $charge['refunds']['data'] ?? [];

        if (! empty($perRefundData)) {
            // Preferred path: iterate individual refund objects keyed on re_xxx.
            foreach ($perRefundData as $refund) {
                $refundId = $refund['id'] ?? null;
                $refundCents = (int) ($refund['amount'] ?? 0);
                if ($refundCents <= 0) {
                    continue;
                }
                $clawbackCents = min((int) round($refundCents * $providerCents / $totalCents), $providerCents);
                if ($clawbackCents <= 0) {
                    continue;
                }
                $this->walletService->recordRefundClawback(
                    $booking,
                    round($clawbackCents / 100, 2),
                    $refundId,
                );
            }
        } else {
            // Fallback: refunds.data absent (some legacy/test payloads).
            // Key on charge id — this path is only hit when no per-refund data is
            // available, so there is no overlap with the service path (which always
            // has a re_xxx).
            $clawbackCents = min((int) round($refundedAmountCents * $providerCents / $totalCents), $providerCents);
            if ($clawbackCents > 0) {
                $this->walletService->recordRefundClawback(
                    $booking,
                    round($clawbackCents / 100, 2),
                    $charge['id'] ?? null,
                );
            }
        }

        BusinessEventEmitter::emit(
            eventCode: 'payment.refunded',
            payload: [
                'booking_id' => $booking->id,
                'amount_refunded_cents' => $refundedAmountCents,
                'currency' => $charge['currency'] ?? null,
                'stripe_charge_id' => $charge['id'] ?? null,
                'stripe_payment_intent_id' => $pi,
                'is_total' => $isTotal,
            ],
            idempotencyKey: 'payment.refunded:'.($charge['id'] ?? $pi),
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );
        BookingAutoPoster::postRefund($booking, $refundedAmountCents);

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'is_total' => $isTotal,
        ]];
    }

    public function handlePaymentIntentSucceeded(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        // 1) Si c'est un payment intent de TIP, confirmCharge le tip
        $this->maybeConfirmTipCharge($intent, $piId);

        [$booking, $volet] = $this->reservationPourIntent($piId);

        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        /*
         * L'ACOMPTE EST DEJA ENCAISSE, ET SA PART PRESTATAIRE DEJA TRANSFEREE.
         *
         * Il est cree en capture automatique avec sa propre `application_fee_amount` et sa
         * destination Connect : Stripe a deja fait le partage au moment de la commande. Le
         * reprendre ici creditrait `provider_amount_cents` -- la part du TOTAL -- pour un
         * encaissement partiel, et une seconde fois a la capture du solde, la cle d'idempotence
         * portant l'identifiant de l'intention.
         *
         * On reconnait donc l'evenement au lieu de l'ignorer -- il concerne bien une reservation
         * connue, et le journal doit pouvoir le dire -- sans toucher ni au statut du solde ni au
         * portefeuille.
         */
        if ($volet === self::VOLET_ACOMPTE) {
            return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
                'booking_id' => $booking->id,
                'volet' => self::VOLET_ACOMPTE,
                'reason' => 'acompte deja encaisse a la commande',
            ]];
        }

        $previousStatus = $booking->payment_status;
        $this->paymentService->syncPaymentIntent($booking);
        $booking->refresh();

        if ($booking->payment_status === 'captured') {
            // recordEarning is idempotent (idempotency_key prevents double-write),
            // so it is safe to call regardless of previousStatus.
            // Bug fix: the original guard ($previousStatus !== 'captured') caused
            // recordEarning to be skipped when captureMissionPayment had already
            // set payment_status='captured' before this webhook arrived, resulting
            // in the wallet never being credited in the standard capture→webhook flow.
            $this->walletService->recordEarning($booking, $intent);

            $feeCents = (int) (data_get($intent, 'charges.data.0.balance_transaction.fee')
                ?? data_get($intent, 'application_fee_amount')
                ?? 0);

            /*
             * LA MÊME GARDE QUE POUR `recordEarning`, ET LE MÊME DÉFAUT — ELLE EST TOMBÉE ICI AUSSI.
             *
             * Le commentaire juste au-dessus raconte que `$previousStatus !== 'captured'` empêchait
             * le crédit du portefeuille quand `captureMissionPayment()` avait déjà posé `captured`
             * avant l'arrivée du webhook. La correction n'a porté que sur `recordEarning` : ces
             * deux appels-ci sont restés derrière la garde cassée.
             *
             * Or c'est le chemin ORDINAIRE. `captureMissionPayment()` écrit `payment_status =
             * 'captured'` puis Stripe notifie : le statut précédent vaut donc déjà `captured`, et
             * ni l'événement métier ni l'écriture comptable n'avaient lieu. Seules les confirmations
             * ASYNCHRONES — celles où Stripe capture sans passer par notre appel — franchissaient la
             * garde. Autrement dit, le grand livre ne recevait que les encaissements minoritaires.
             *
             * Un TÉMOIN l'a découvert : il affirmait qu'un encaissement normal écrit toujours sa
             * ligne, et il était rouge. Écrit pour prouver qu'on n'avait rien cassé, il a montré ce
             * qui était déjà cassé.
             *
             * RIEN NE SE DUPLIQUE POUR AUTANT, ET C'EST VÉRIFIÉ : `WebhookDispatcher::emit()` rend
             * l'événement existant quand la clé d'idempotence a déjà servi, et `postIdempotent()`
             * rend le lot existant. La garde protégeait d'un risque que les deux appelés écartent
             * déjà eux-mêmes.
             */
            BusinessEventEmitter::emit(
                eventCode: 'payment.succeeded',
                payload: [
                    'booking_id' => $booking->id,
                    'amount_cents' => (int) ($intent['amount'] ?? 0),
                    'currency' => $intent['currency'] ?? null,
                    'stripe_payment_intent_id' => $piId,
                    'fees_cents' => $feeCents,
                ],
                idempotencyKey: 'payment.succeeded:'.$piId,
                sourceType: Booking::class,
                sourceId: (int) $booking->id,
            );
            BookingAutoPoster::postPayment($booking, $feeCents);
        }

        /*
         * DES FRAIS D'ANNULATION SONT DE L'ARGENT ENCAISSÉ, ET ILS N'ENTRAIENT DANS AUCUN LIVRE.
         *
         * Tout le reste était prêt : le plan comptable déclare `708 Produits annexes (frais
         * d'annulation)` et `ChartOfAccounts::salesAccount('cancellation_fee')` le renvoie. Rien ne
         * l'appelait. La garde `payment_status === 'captured'` juste au-dessus — indispensable pour
         * que le prestataire ne soit pas crédité d'une prestation jamais faite — écartait du même
         * geste l'écriture comptable, qui, elle, devait avoir lieu.
         *
         * ── PAS DE GARDE SUR `$previousStatus`, ET C'EST DÉLIBÉRÉ ─────────────────────────────
         *
         * `capturerLesFraisDAnnulation()` pose `fee_captured` AVANT que le webhook n'arrive : au
         * moment où l'on passe ici, le statut précédent vaut déjà `fee_captured`. Le motif
         * `$previousStatus !== …` employé quelques lignes plus haut sauterait donc l'écriture à
         * tous les coups. C'est exactement le défaut que le commentaire de `recordEarning` raconte,
         * et l'imiter par symétrie le reproduirait. L'idempotence est portée par
         * `postIdempotent()`, sur une clé qui lui est propre.
         */
        if ($booking->payment_status === MissionPaymentService::STATUT_FRAIS_CAPTURES) {
            [$fraisCents, $partPrestataireCents] = $this->partagerLesFraisDAnnulation($booking, $intent);

            BookingAutoPoster::postCancellationFee(
                $booking,
                $fraisCents,
                (int) (data_get($intent, 'charges.data.0.balance_transaction.fee') ?? 0),
                $partPrestataireCents,
            );
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'transitioned_to_captured' => $booking->payment_status === 'captured' && $previousStatus !== 'captured',
        ]];
    }

    /**
     * COMBIEN A ÉTÉ PRIS, ET COMBIEN EN EST REPARTI CHEZ LE PRESTATAIRE.
     *
     * LE MONTANT vient d'abord de notre propre trace : `capturerLesFraisDAnnulation()` écrit
     * `metadata.frais_annulation.captures_cents`, qui EST la définition des frais. On se rabat sur
     * `amount_received` quand elle manque — une capture faite hors de ce code, depuis le tableau de
     * bord Stripe par exemple.
     *
     * LE PARTAGE SE LIT, IL NE SE DEVINE PAS. L'empreinte est une charge à destination : elle porte
     * `transfer_data.destination` et une `application_fee_amount` calculée sur la commande entière.
     * Ce que Stripe fait de cette commission lors d'une capture PARTIELLE décide si la plateforme
     * garde tout ou si une part file chez le prestataire — et cela ne s'exerce nulle part ici, la
     * clé du dépôt faisant onze caractères. On lit donc la commission RÉELLEMENT appliquée sur la
     * charge et on en déduit le reste.
     *
     * L'ABSENCE DE `application_fee_amount` VAUT « LA PLATEFORME GARDE TOUT », et c'est le choix
     * prudent dans les deux cas réels : soit l'intention n'a pas de destinataire — la variante sans
     * prestataire n'en pose aucun — soit la charge n'expose pas le champ, et fabriquer une dette
     * prestataire depuis un champ manquant inventerait un passif.
     *
     * @param  array<string, mixed>  $intent
     * @return array{0: int, 1: int} Frais encaissés, part partie chez le prestataire.
     */
    private function partagerLesFraisDAnnulation(Booking $booking, array $intent): array
    {
        $fraisCents = (int) (data_get($booking->metadata, 'frais_annulation.captures_cents')
            ?? data_get($intent, 'amount_received')
            ?? 0);

        if ($fraisCents <= 0) {
            return [0, 0];
        }

        $commissionPlateforme = data_get($intent, 'application_fee_amount');

        if ($commissionPlateforme === null) {
            return [$fraisCents, 0];
        }

        return [$fraisCents, max(0, $fraisCents - (int) $commissionPlateforme)];
    }

    /**
     * Si un PaymentIntent succeeded correspond à un BookingTip, le confirmCharge.
     * Filtre via metadata.tip_id OU lookup stripe_payment_intent_id sur booking_tips.
     */
    public function maybeConfirmTipCharge(array $intent, string $piId): void
    {
        try {
            if (! class_exists(BookingTip::class)) {
                return;
            }
            $tip = BookingTip::query()
                ->where('stripe_payment_intent_id', $piId)
                ->where('status', BookingTip::STATUS_PENDING)
                ->first();
            if (! $tip) {
                $tipId = data_get($intent, 'metadata.tip_id');
                if ($tipId) {
                    $tip = BookingTip::query()
                        ->where('id', $tipId)
                        ->where('status', BookingTip::STATUS_PENDING)
                        ->first();
                }
            }
            if ($tip) {
                app(TipService::class)->confirmCharge($tip, $piId);
            }
        } catch (\Throwable $e) {
            Log::warning('[tips_webhook] confirmCharge failed', [
                'pi_id' => $piId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handlePaymentIntentFailed(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        [$booking, $volet] = $this->reservationPourIntent($piId);

        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        // Meme raison que ci-dessus : un acompte en echec ne dit rien du solde, et ecrire
        // `failed` sur la reservation ferait passer pour perdu un solde parfaitement autorise.
        $alreadyFailed = $booking->payment_status === 'failed' || $volet === self::VOLET_ACOMPTE;

        if (! $alreadyFailed) {
            $booking->forceFill([
                'payment_status' => 'failed',
                'payment_failed_at' => now(),
            ])->save();

            // Notify the client (soft-fail: don't let notification errors abort the webhook)
            try {
                $client = $booking->client ?? User::find($booking->customer_user_id ?? $booking->client_id);
                if ($client) {
                    $failureMessage = data_get($intent, 'last_payment_error.message');
                    $client->notify(new PaymentFailedNotification($booking, $failureMessage));
                }
            } catch (\Throwable $e) {
                Log::warning('[payment_failed_webhook] notification failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        BusinessEventEmitter::emit(
            eventCode: 'payment.failed',
            payload: [
                'booking_id' => $booking->id,
                'amount_cents' => (int) ($intent['amount'] ?? 0),
                'currency' => $intent['currency'] ?? null,
                'stripe_payment_intent_id' => $piId,
                'failure_message' => data_get($intent, 'last_payment_error.message'),
                'failure_code' => data_get($intent, 'last_payment_error.code'),
            ],
            idempotencyKey: 'payment.failed:'.$piId,
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['booking_id' => $booking->id]];
    }

    public function handleTransferCreated(array $transfer): array
    {
        $stripeTransferId = $transfer['id'] ?? null;
        if (! $stripeTransferId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $existing = ProviderWalletTransaction::query()
            ->where('stripe_transfer_id', $stripeTransferId)
            ->exists();

        if ($existing) {
            return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['already' => true]];
        }

        // Sync stripe_transfer_id + payout_status on booking if referenced in metadata
        $bookingId = $transfer['metadata']['booking_id'] ?? null;
        if ($bookingId) {
            $booking = Booking::query()->find($bookingId);
            if ($booking && empty($booking->stripe_transfer_id)) {
                $booking->forceFill([
                    'stripe_transfer_id' => $stripeTransferId,
                    'payout_status' => 'transferred',
                ])->save();

                return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
                    'booking_id' => $booking->id,
                    'stripe_transfer_id' => $stripeTransferId,
                ]];
            }
        }

        return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'transfer_noted_no_action']];
    }
}
