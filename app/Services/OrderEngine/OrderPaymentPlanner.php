<?php

namespace App\Services\OrderEngine;

use App\Models\Booking;
use App\Services\Payments\CommissionService;
use App\Support\Domain\PaymentPlan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\PaymentIntent;

/**
 * Comment le client règle : tout retenu, ou acompte puis solde.
 *
 * Le défaut est la RETENUE INTÉGRALE — rien n'est débité avant l'intervention, la somme est
 * seulement bloquée. C'est ce qui se comprend le mieux, et c'est ce qui protège le mieux le
 * client : il n'a rien payé tant que rien n'a été fait.
 *
 * L'ACOMPTE N'EXISTE QUE POUR LES GROS MONTANTS. Bloquer deux mille euros sur une carte pendant
 * une semaine est intenable : la limite du client est mangée par une somme qu'il n'a même pas
 * dépensée. En dessous du seuil, proposer un acompte n'apporte rien et complique une décision qui
 * doit rester d'un seul mouvement.
 *
 * LA COMMISSION NE SE DIVISE PAS À LA LOUCHE. Deux paiements, donc deux commissions Stripe — et
 * leur somme doit valoir EXACTEMENT celle qu'aurait produite un paiement unique. Un centime perdu
 * à chaque arrondi, sur dix mille commandes, est un écart comptable que personne ne sait plus
 * expliquer. Le reste d'arrondi est donc porté par le solde, jamais dispersé.
 */
class OrderPaymentPlanner
{
    public function __construct(
        protected CommissionService $commissions,
    ) {}

    /**
     * Les formules ouvertes à cette réservation, montants compris.
     *
     * Rendues plutôt qu'imposées : l'écran doit pouvoir les présenter côte à côte avec ce que
     * chacune coûte AUJOURD'HUI. Un client qui découvre après coup qu'on lui a prélevé un acompte
     * est un client perdu.
     *
     * @return list<array{plan: string, label: string, due_now_cents: int, held_cents: int, detail: string}>
     */
    public function optionsFor(Booking $booking): array
    {
        $total = (int) $this->commissions->calculateForBooking($booking)['total_cents'];

        $options = [[
            'plan' => PaymentPlan::FULL,
            'label' => PaymentPlan::label(PaymentPlan::FULL),
            'due_now_cents' => 0,
            'held_cents' => $total,
            'detail' => 'Rien n’est débité maintenant. Le montant est simplement bloqué et prélevé après l’intervention.',
        ]];

        if ($this->depositIsAvailable($booking, $total)) {
            $deposit = $this->depositAmount($total);

            $options[] = [
                'plan' => PaymentPlan::DEPOSIT,
                'label' => PaymentPlan::label(PaymentPlan::DEPOSIT),
                'due_now_cents' => $deposit,
                'held_cents' => $total - $deposit,
                'detail' => sprintf(
                    'Un acompte de %s € est débité aujourd’hui ; le solde est bloqué et prélevé après l’intervention.',
                    number_format($deposit / 100, 2, ',', ' '),
                ),
            ];
        }

        return $options;
    }

    /**
     * Un acompte n'a de sens qu'au-dessus d'un certain montant, et jamais sur un devis.
     *
     * Sur une prestation « sur devis », le total n'est pas connu : un pourcentage d'un montant
     * inconnu ne veut rien dire, et le calculer sur une estimation provisoire ferait payer au
     * client un acompte sur un chiffre qui n'engage personne.
     */
    public function depositIsAvailable(Booking $booking, ?int $total = null): bool
    {
        if (! (bool) Config::get('order_engine.deposit_enabled', true)) {
            return false;
        }

        if (blank($booking->estimated_price) && blank($booking->devis_estime)) {
            return false;
        }

        $total ??= (int) $this->commissions->calculateForBooking($booking)['total_cents'];

        return $total >= (int) Config::get('order_engine.deposit_threshold_cents', 50000);
    }

    /** La part débitée aujourd'hui. */
    public function depositAmount(int $totalCents): int
    {
        $rate = (float) Config::get('order_engine.deposit_rate', 0.30);

        return (int) round($totalCents * $rate);
    }

    /**
     * Le partage de la commission entre l'acompte et le solde.
     *
     * La somme des deux parts vaut EXACTEMENT la commission d'un paiement unique : le reste
     * d'arrondi est porté par le solde. Sans cette règle, chaque commande perdrait ou gagnerait un
     * centime, et la réconciliation Stripe↔comptabilité deviendrait un exercice de devinette.
     *
     * @return array{deposit_fee_cents: int, balance_fee_cents: int}
     */
    public function splitFee(int $totalCents, int $depositCents, int $totalFeeCents): array
    {
        if ($totalCents <= 0) {
            return ['deposit_fee_cents' => 0, 'balance_fee_cents' => $totalFeeCents];
        }

        $depositFee = (int) floor($totalFeeCents * $depositCents / $totalCents);

        return [
            'deposit_fee_cents' => $depositFee,
            // Le solde absorbe le reste : la somme est exacte par construction, pas par chance.
            'balance_fee_cents' => $totalFeeCents - $depositFee,
        ];
    }

    /**
     * Prélève l'acompte et bloque le solde.
     *
     * Deux PaymentIntent : l'acompte est capturé immédiatement, le solde reste en attente. Les
     * confondre rendrait impossible de dire, six mois plus tard, ce qui a été encaissé et ce qui
     * n'était qu'une empreinte.
     *
     * @throws ValidationException
     */
    public function authorizeWithDeposit(Booking $booking, string $paymentMethodId): Booking
    {
        $booking->loadMissing(['client', 'employe']);
        $provider = $booking->employe;

        if (! $provider || ! $provider->canReceiveStripeConnectPayments()) {
            throw ValidationException::withMessages([
                'payment' => ['Le professionnel n’a pas encore terminé la configuration de ses paiements.'],
            ]);
        }

        if (! $this->depositIsAvailable($booking)) {
            throw ValidationException::withMessages([
                'payment' => ['L’acompte n’est pas proposé pour cette commande.'],
            ]);
        }

        if (! $booking->client?->stripe_id) {
            $booking->client?->createAsStripeCustomer();
            $booking->refresh()->loadMissing('client');
        }

        $commission = $this->commissions->calculateForBooking($booking);
        $total = (int) $commission['total_cents'];
        $deposit = $this->depositAmount($total);
        $balance = $total - $deposit;
        $fees = $this->splitFee($total, $deposit, (int) $commission['platform_fee_cents']);

        $currency = strtolower($booking->pricing_snapshot['currency'] ?? 'eur');

        // L'acompte : débité tout de suite.
        $depositIntent = PaymentIntent::create([
            'amount' => $deposit,
            'currency' => $currency,
            'customer' => $booking->client->stripe_id,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'capture_method' => 'automatic',
            'application_fee_amount' => $fees['deposit_fee_cents'],
            'transfer_data' => ['destination' => $provider->stripe_connect_account_id],
            'metadata' => $this->metadata($booking, $provider->id, 'deposit'),
        ]);

        // Le solde : bloqué, prélevé après l'intervention.
        $balanceIntent = PaymentIntent::create([
            'amount' => $balance,
            'currency' => $currency,
            'customer' => $booking->client->stripe_id,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'capture_method' => 'manual',
            'application_fee_amount' => $fees['balance_fee_cents'],
            'transfer_data' => ['destination' => $provider->stripe_connect_account_id],
            'metadata' => $this->metadata($booking, $provider->id, 'balance'),
        ]);

        $booking->update([
            'payment_plan' => PaymentPlan::DEPOSIT,
            'deposit_payment_intent_id' => $depositIntent->id,
            'deposit_amount_cents' => $deposit,
            'deposit_captured_at' => now(),
            'stripe_payment_intent_id' => $balanceIntent->id,
            'stripe_connect_account_id' => $provider->stripe_connect_account_id,
            'payment_amount_cents' => $total,
            'platform_fee_cents' => (int) $commission['platform_fee_cents'],
            'provider_amount_cents' => (int) $commission['provider_payout_cents'],
            'payment_status' => 'authorized',
            'payment_authorized_at' => now(),
        ]);

        return $booking->fresh();
    }

    /**
     * Libère les sommes bloquées quand la réservation change de professionnel.
     *
     * C'EST UNE GARANTIE D'ARGENT, pas une propreté d'écriture. L'autorisation Stripe désigne le
     * compte du prestataire comme destination : si un autre intervient, l'argent partirait chez
     * quelqu'un qui n'a rien fait. La retenue est donc annulée AVANT toute réassignation, et le
     * client sera redébité une fois le nouveau professionnel connu.
     *
     * L'acompte DÉJÀ DÉBITÉ n'est pas touché ici : le rembourser ou le transférer est une décision
     * commerciale, pas un effet de bord d'une réassignation. Il est signalé pour être traité.
     *
     * @return array{released: bool, deposit_to_settle_cents: int}
     */
    public function releaseForReassignment(Booking $booking): array
    {
        $held = $booking->stripe_payment_intent_id;
        $depositToSettle = (int) ($booking->deposit_captured_at ? $booking->deposit_amount_cents : 0);

        if (! $held || $booking->payment_status !== 'authorized') {
            return ['released' => false, 'deposit_to_settle_cents' => $depositToSettle];
        }

        try {
            PaymentIntent::retrieve($held)->cancel();
        } catch (\Throwable $e) {
            /*
             * L'annulation a échoué chez Stripe. On NE marque PAS la retenue comme libérée : la
             * croire annulée alors qu'elle tient encore ferait créer une seconde empreinte sur la
             * carte du client, et il verrait deux fois le montant bloqué.
             */
            Log::error('OrderPaymentPlanner: libération impossible', [
                'booking_id' => $booking->id,
                'payment_intent' => $held,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'payment' => ['La retenue bancaire n’a pas pu être libérée. Réessayez avant de réassigner.'],
            ]);
        }

        $booking->update([
            'stripe_payment_intent_id' => null,
            'stripe_connect_account_id' => null,
            'payment_status' => 'pending',
            'payment_authorized_at' => null,
        ]);

        return ['released' => true, 'deposit_to_settle_cents' => $depositToSettle];
    }

    /** @return array<string, mixed> */
    protected function metadata(Booking $booking, int $providerId, string $part): array
    {
        return [
            'rendez_vous_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'client_id' => $booking->client_id,
            'employee_id' => $providerId,
            // La part sert à la réconciliation : sans elle, deux intents du même montant seraient
            // indiscernables dans le tableau de bord Stripe.
            'payment_part' => $part,
        ];
    }
}
