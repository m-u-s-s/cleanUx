<?php

namespace App\Services\Missions\OnSite;

use App\Events\Missions\MissionExtraProposed;
use App\Models\Mission;
use App\Models\MissionExtra;
use App\Models\User;
use App\Notifications\MissionExtraNotification;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Missions\MissionHistoryService;
use App\Services\Payments\CommissionService;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;

/**
 * LES SUPPLÉMENTS PROPOSÉS SUR PLACE — proposer, accepter, refuser, encaisser.
 *
 * Le prestataire arrive, constate que les vitres n'étaient pas au devis, et propose vingt-cinq
 * euros. Sans ce chemin il n'a que deux mauvaises réponses — le faire gratuitement, ou ne pas le
 * faire — et une troisième pire que les deux : s'arranger en espèces, ce qui sort l'argent de la
 * plateforme et le client de toute protection, garantie comprise.
 *
 * LE DEVIS D'ORIGINE RESTE FIGÉ. Un supplément est une ligne additionnelle, jamais une correction
 * du montant initial : le client doit pouvoir relire ce qu'il a accepté au départ. Un devis qui
 * change après coup est exactement ce qui fait perdre confiance, même quand le changement est
 * justifié.
 *
 * L'ACCORD ET L'ENCAISSEMENT SONT DEUX ÉTATS. `approved` porte la parole du client, `charged` dit
 * que l'argent a bougé. Les confondre ferait réclamer deux fois un supplément dont le prélèvement a
 * échoué, ou ne jamais réclamer celui qu'on croyait payé. Un échec de prélèvement laisse donc
 * l'extra en `approved` — le travail reste dû, et la reprise est possible.
 */
class MissionExtraService
{
    /**
     * Le plafond d'un supplément proposé depuis le terrain.
     *
     * Un supplément est un ajustement, pas une seconde intervention. Au-delà, c'est un nouveau devis
     * qui doit passer par le parcours normal — avec ses conditions d'annulation et sa protection.
     */
    public const MAX_PRICE_CENTS = 50000;

    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected CommissionService $commissionService,
    ) {}

    /**
     * Le prestataire propose un supplément.
     *
     * @param  int  $priceCents  montant TTC, en centimes
     */
    public function propose(
        Mission $mission,
        User $author,
        string $label,
        int $priceCents,
        ?string $description = null,
        ?int $priceQuoteId = null,
    ): MissionExtra {
        $this->assignmentStatusService->assertAssignedToMission($mission, $author);

        if ($priceCents <= 0) {
            throw new DomainException('Un supplément a un prix.');
        }

        if ($priceCents > self::MAX_PRICE_CENTS) {
            throw new DomainException(
                'Au-delà de '.(int) (self::MAX_PRICE_CENTS / 100).' €, c’est un nouveau devis : '.
                'le client doit y retrouver ses conditions d’annulation et sa protection.',
            );
        }

        if (trim($label) === '') {
            throw new DomainException('Dites au client ce qu’il paie.');
        }

        $extra = MissionExtra::query()->create([
            'mission_id' => $mission->id,
            'proposed_by_user_id' => $author->id,
            'label' => trim($label),
            'description' => $description !== null ? trim($description) : null,
            'price_cents' => $priceCents,
            'currency' => strtoupper((string) ($mission->booking->currency ?? 'EUR')),
            'price_quote_id' => $priceQuoteId,
            'status' => MissionExtra::STATUS_PROPOSED,
        ]);

        $this->prevenirLeClient($mission, $extra);

        event(new MissionExtraProposed($mission, $extra->fresh()));

        app(MissionHistoryService::class)->log(
            $mission,
            $author,
            'mission_extra_proposed',
            'Supplément proposé',
            $extra->label.' — '.number_format($extra->montantEnEuros(), 2, ',', ' ').' €',
            ['extra_id' => $extra->id],
        );

        return $extra->fresh();
    }

    /**
     * Le client accepte — en un geste, depuis sa notification ou son écran de suivi.
     *
     * L'ENCAISSEMENT SUIT, MAIS NE CONDITIONNE PAS L'ACCORD. Si Stripe refuse, l'accord reste
     * enregistré : le prestataire a le droit de faire le travail, et la plateforme a une créance
     * qu'elle peut représenter. Faire dépendre l'accord du prélèvement ferait perdre les deux.
     */
    public function approve(MissionExtra $extra, User $client): MissionExtra
    {
        $this->assertEnAttente($extra);

        $extra->forceFill([
            'status' => MissionExtra::STATUS_APPROVED,
            'approved_at' => now(),
        ])->save();

        app(MissionHistoryService::class)->log(
            $extra->mission,
            $client,
            'mission_extra_approved',
            'Supplément accepté',
            $extra->label,
            ['extra_id' => $extra->id],
        );

        $this->prelever($extra->fresh());

        return $extra->fresh();
    }

    /**
     * Le client refuse. C'est une réponse, pas une panne : elle se conserve et se relit.
     */
    public function decline(MissionExtra $extra, User $client): MissionExtra
    {
        $this->assertEnAttente($extra);

        $extra->forceFill([
            'status' => MissionExtra::STATUS_DECLINED,
            'declined_at' => now(),
        ])->save();

        app(MissionHistoryService::class)->log(
            $extra->mission,
            $client,
            'mission_extra_declined',
            'Supplément refusé',
            $extra->label,
            ['extra_id' => $extra->id],
        );

        return $extra->fresh();
    }

    /**
     * @return Collection<int, MissionExtra>
     */
    public function pourLaMission(Mission $mission): Collection
    {
        return MissionExtra::query()
            ->where('mission_id', $mission->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function presenter(MissionExtra $extra): array
    {
        return [
            'id' => $extra->id,
            'label' => $extra->label,
            'description' => $extra->description,
            'price_cents' => $extra->price_cents,
            'price' => $extra->montantEnEuros(),
            'currency' => $extra->currency,
            'status' => $extra->status,
            // Le client a besoin de savoir s'il DOIT encore répondre : c'est la seule question que
            // pose cet écran.
            'awaiting_client' => $extra->estEnAttente(),
            'proposed_by' => $extra->proposedBy?->name,
            'proposed_at' => $extra->created_at?->toIso8601String(),
            'approved_at' => $extra->approved_at?->toIso8601String(),
            'declined_at' => $extra->declined_at?->toIso8601String(),
            'charged_at' => $extra->charged_at?->toIso8601String(),
        ];
    }

    protected function assertEnAttente(MissionExtra $extra): void
    {
        if (! $extra->estEnAttente()) {
            // Répondre deux fois n'est pas une erreur de l'utilisateur : c'est un double appui, ou
            // deux appareils. On refuse sans dramatiser, mais on refuse — sinon un refus effacerait
            // un accord déjà encaissé.
            throw new DomainException('Ce supplément a déjà reçu une réponse.');
        }
    }

    /**
     * PRÉLÈVEMENT INCRÉMENTAL — même mécanique que le paiement principal.
     *
     * Charge à destination du compte Connect du prestataire, commission de la plateforme retenue par
     * le même calcul que le devis d'origine : sans cela, le supplément échapperait à la commission
     * et le portefeuille interne divergerait de ce que Stripe a réellement transféré.
     *
     * L'ÉCHEC EST SILENCIEUX POUR L'UTILISATEUR ET BRUYANT DANS LES JOURNAUX. Le client vient de
     * dire oui ; lui montrer une erreur de paiement à cet instant ferait annuler un accord qui est
     * acquis. L'extra reste `approved`, la créance existe, et la reprise se fait plus tard.
     */
    protected function prelever(MissionExtra $extra): void
    {
        $mission = $extra->mission;
        $booking = $mission?->booking;
        $prestataire = $booking?->employe;

        if (! $booking || ! $prestataire?->canReceiveStripeConnectPayments() || ! $booking->client?->stripe_id) {
            Log::info('Extra approuvé sans prélèvement immédiat', [
                'extra_id' => $extra->id,
                'raison' => 'compte Connect ou client Stripe indisponible',
            ]);

            return;
        }

        try {
            $commission = $this->commissionService->calculateForAmount($extra->price_cents, $prestataire);

            $intent = PaymentIntent::create([
                'amount' => $extra->price_cents,
                'currency' => strtolower($extra->currency),
                'customer' => $booking->client->stripe_id,
                'confirm' => false,
                'application_fee_amount' => $commission['platform_fee_cents'],
                'transfer_data' => ['destination' => (string) $prestataire->stripe_connect_account_id],
                'metadata' => [
                    'mission_extra_id' => (string) $extra->id,
                    'mission_id' => (string) $mission->id,
                    'booking_reference' => (string) $booking->booking_reference,
                ],
            ]);

            $extra->forceFill([
                'status' => MissionExtra::STATUS_CHARGED,
                'charged_at' => now(),
                'stripe_payment_intent_id' => $intent->id,
            ])->save();
        } catch (\Throwable $e) {
            report($e);

            Log::warning('Prélèvement du supplément impossible', [
                'extra_id' => $extra->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function prevenirLeClient(Mission $mission, MissionExtra $extra): void
    {
        $client = $mission->booking?->client;

        if (! $client) {
            return;
        }

        // Soft-fail : un supplément proposé ne doit pas échouer parce qu'un canal de notification
        // est indisponible. Le client le verra sur son écran de suivi.
        try {
            $client->notify(new MissionExtraNotification($mission, $extra));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
