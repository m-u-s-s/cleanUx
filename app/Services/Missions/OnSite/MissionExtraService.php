<?php

namespace App\Services\Missions\OnSite;

use App\Events\Missions\MissionExtraProposed;
use App\Models\Booking;
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
     * Rejouer un prélèvement qui n'avait pas abouti.
     *
     * Même chemin que le prélèvement d'origine — surtout pas une seconde implémentation : deux
     * façons de débiter la même créance finiraient par appliquer deux commissions différentes.
     * Cette méthode n'existe que pour donner un point d'entrée public à la commande de reprise.
     */
    public function reprendreLePrelevement(MissionExtra $extra): void
    {
        if ($extra->status !== MissionExtra::STATUS_APPROVED) {
            return;
        }

        $this->prelever($extra);
    }

    /**
     * PRÉLÈVEMENT INCRÉMENTAL — même mécanique que le paiement principal.
     *
     * Charge à destination du compte Connect du prestataire, commission retenue par le même calcul
     * que le devis d'origine : sans cela, le supplément échapperait à la commission et le
     * portefeuille interne divergerait de ce que Stripe a réellement transféré.
     *
     * CE QUE CETTE MÉTHODE NE FAISAIT PAS, ET QUI EST LE PROPOS DE SA RÉÉCRITURE.
     *
     * Elle créait l'intention avec `confirm: false`, sans moyen de paiement et sans `off_session` :
     * l'intention naissait en `requires_payment_method`, AUCUN euro ne bougeait — puis l'extra était
     * marqué `charged` quoi qu'il arrive. Tout supplément accepté par un client était donc
     * enregistré comme encaissé sans l'être, et rien nulle part ne pouvait le rattraper : ni la
     * comptabilité, ni le portefeuille du prestataire, ni le webhook, qui ne connaît pas
     * `mission_extra_id`.
     *
     * Trois corrections, indissociables :
     *   1. la carte du client est celle de SA réservation, reprise sur l'intention d'origine ;
     *   2. l'intention est confirmée hors session ;
     *   3. `charged` n'est écrit QUE si Stripe a dit `succeeded`.
     *
     * L'ÉCHEC RESTE SILENCIEUX POUR L'UTILISATEUR ET BRUYANT DANS LES JOURNAUX. Le client vient de
     * dire oui ; lui montrer une erreur de paiement à cet instant ferait annuler un accord acquis.
     * L'extra reste `approved` — la créance existe, et `extras:reprendre-les-prelevements` la
     * reprend. Cette phrase était déjà écrite ici ; elle est désormais vraie.
     */
    protected function prelever(MissionExtra $extra): void
    {
        $mission = $extra->mission;
        $booking = $mission?->booking;

        if (! $booking) {
            $this->noterLEchec($extra, 'réservation introuvable');

            return;
        }

        /*
         * LE MÊME PRESTATAIRE QUE LA COMMISSION, pas `employe` tout seul.
         *
         * `employe_id` est la colonne historique : une réservation moderne porte
         * `assigned_provider_user_id` et laisse la première vide. En ne lisant qu'elle, ce
         * prélèvement sortait par un `Log::info` sur toutes les réservations récentes — donc sur
         * celles qui comptent.
         */
        $prestataire = $booking->assignedProvider ?? $booking->employe;

        if (! $prestataire?->canReceiveStripeConnectPayments() || ! $booking->client?->stripe_id) {
            $this->noterLEchec($extra, 'compte Connect ou client Stripe indisponible');

            return;
        }

        $carte = $this->carteDuClient($booking);

        if ($carte === null) {
            $this->noterLEchec($extra, 'aucun moyen de paiement réutilisable sur la réservation');

            return;
        }

        try {
            $commission = $this->commissionService->calculateForAmount($extra->price_cents, $prestataire);

            $intent = PaymentIntent::create([
                'amount' => $extra->price_cents,
                'currency' => strtolower($extra->currency),
                'customer' => $booking->client->stripe_id,
                'payment_method' => $carte,
                /*
                 * `confirm` + `off_session` : le client n'est plus devant son écran, il a accepté
                 * le supplément et l'intervention continue. C'est exactement le cas d'usage que
                 * Stripe nomme « off-session » — et sans les deux, rien n'est débité.
                 */
                'confirm' => true,
                'off_session' => true,
                'application_fee_amount' => $commission['platform_fee_cents'],
                'transfer_data' => ['destination' => (string) $prestataire->stripe_connect_account_id],
                'metadata' => [
                    'mission_extra_id' => (string) $extra->id,
                    'mission_id' => (string) $mission->id,
                    'booking_reference' => (string) $booking->booking_reference,
                ],
            ]);

            /*
             * `charged` SEULEMENT SI STRIPE L'A DIT.
             *
             * Une carte peut réclamer une authentification forte hors session : l'intention part
             * alors en `requires_action` et rien n'est encaissé. L'écrire `charged` reviendrait à
             * refaire exactement le défaut qu'on corrige.
             */
            if (($intent->status ?? null) !== 'succeeded') {
                $extra->forceFill(['stripe_payment_intent_id' => $intent->id])->save();
                $this->noterLEchec($extra, 'intention non aboutie : '.($intent->status ?? 'statut inconnu'));

                return;
            }

            $extra->forceFill([
                'status' => MissionExtra::STATUS_CHARGED,
                'charged_at' => now(),
                'stripe_payment_intent_id' => $intent->id,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
            $this->noterLEchec($extra, $e->getMessage());
        }
    }

    /**
     * La carte à débiter : celle que le client a déjà utilisée pour CETTE réservation.
     *
     * On la relit sur l'intention d'origine plutôt que de deviner un moyen de paiement par défaut
     * sur le compte client. C'est la carte pour laquelle il a donné son accord sur cette
     * intervention-là, et celle qu'il verra sur son relevé à côté du montant principal.
     */
    protected function carteDuClient(Booking $booking): ?string
    {
        if (! filled($booking->stripe_payment_intent_id)) {
            return null;
        }

        try {
            $origine = PaymentIntent::retrieve((string) $booking->stripe_payment_intent_id);

            $carte = $origine->payment_method ?? null;

            return filled($carte) ? (string) $carte : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * La créance reste, et elle est datée.
     *
     * `metadata` porte le motif et l'horodatage : sans eux, un administrateur voyant un extra
     * `approved` depuis trois jours n'a aucun moyen de savoir si le prélèvement a été tenté.
     */
    protected function noterLEchec(MissionExtra $extra, string $motif): void
    {
        Log::warning('Prélèvement du supplément impossible', [
            'extra_id' => $extra->id,
            'motif' => $motif,
        ]);

        $extra->forceFill([
            'metadata' => array_merge((array) ($extra->metadata ?? []), [
                'derniere_tentative_de_prelevement' => now()->toIso8601String(),
                'motif_du_dernier_echec' => $motif,
            ]),
        ])->save();
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
