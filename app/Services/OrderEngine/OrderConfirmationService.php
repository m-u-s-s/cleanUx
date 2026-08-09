<?php

namespace App\Services\OrderEngine;

use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\User;
use App\Services\Payments\MissionPaymentService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PaymentPlan;
use App\Support\HumanReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La confirmation : le panier devient une réservation.
 *
 * C'est le seul moment du parcours où une identité est nécessaire — et c'est délibérément le
 * dernier. Tout ce qui précède, y compris le prix, s'est fait sans compte.
 *
 * IDEMPOTENT, et ce n'est pas un confort. Un double-clic, un rechargement, un retour arrière du
 * navigateur : chacun peut renvoyer la confirmation. Sans garde, le client se retrouverait avec
 * deux réservations et deux pré-autorisations pour une seule intervention — et c'est lui qui
 * découvrirait le doublon sur son relevé bancaire.
 *
 * LE DEVIS EST FIGÉ ICI. Le prix affiché au moment du clic est celui qui engage : recalculer plus
 * tard exposerait le client à un montant différent de celui qu'il a accepté, parce qu'un
 * administrateur aura modifié une grille entre-temps.
 */
class OrderConfirmationService
{
    public function __construct(
        protected OrderDraftManager $drafts,
        protected BundleComposer $bundles,
        protected AsapDispatchService $dispatch,
    ) {}

    /**
     * Transforme le panier en réservation.
     *
     * @throws ValidationException si le panier n'est pas confirmable
     */
    public function confirm(OrderDraft $draft, User $client): OrderDraft
    {
        // Rejouer une confirmation rend la même chose : c'est ce qui protège du double envoi.
        if ($draft->status === OrderDraftStatus::CONVERTED) {
            return $draft;
        }

        /*
         * DERNIÈRE CHANCE DE RÉSOUDRE LA ZONE — avant de refuser.
         *
         * Un panier peut arriver ici sans zone : recomposé depuis une clé de rattrapage, créé par
         * l'API, converti depuis un rendez-vous. Refuser sans avoir essayé perdrait une commande
         * parfaitement servable ; passer sans zone donnerait au dispatch une réservation qu'il ne
         * sait pas servir. On essaie, PUIS on refuse si vraiment rien ne couvre cette adresse.
         */
        app(ZonePricingResolver::class)->ensureZoneFor($draft);

        $this->assertConfirmable($draft->fresh());

        return DB::transaction(function () use ($draft, $client) {
            /** @var OrderDraft $locked */
            $locked = OrderDraft::query()->lockForUpdate()->findOrFail($draft->id);

            // Deuxième lecture, sous verrou : deux requêtes simultanées passent toutes deux la
            // vérification ci-dessus, et une seule doit créer la réservation.
            if ($locked->status === OrderDraftStatus::CONVERTED) {
                return $locked;
            }

            $quote = $this->bundles->consolidatedQuote($locked);
            $bookings = [];

            foreach ($quote['items'] as $line) {
                $bookings[] = $this->createBooking($locked, $client, $line['item'], $line['quote']);
            }

            $locked->update([
                'client_id' => $client->id,
                'status' => OrderDraftStatus::CONVERTED,
                'converted_at' => now(),
                'converted_booking_id' => $bookings[0]->id ?? null,
                // Le devis est FIGÉ : c'est le prix accepté qui engage, pas celui qu'un
                // recalcul donnerait demain.
                'estimate_min_cents' => $quote['order']->minCents,
                'estimate_max_cents' => $quote['order']->maxCents,
                'total_cents' => $quote['order']->minCents,
            ]);

            /*
             * En mode immédiat, confirmer OUVRE la recherche — dans la même transaction que la
             * réservation. Confirmer une course « dès que possible » sans lancer la recherche
             * laisserait le client devant un écran d'attente que rien n'alimente.
             */
            if ($locked->mode === OrderMode::ASAP) {
                foreach ($quote['items'] as $line) {
                    $this->dispatch->open($line['item']->fresh(), (float) $locked->lat, (float) $locked->lng);
                }
            }

            return $locked->fresh();
        });
    }

    /**
     * Ce qui manque encore pour confirmer.
     *
     * Rendu plutôt que levé : l'écran doit pouvoir griser son bouton et DIRE pourquoi, au lieu de
     * laisser le client cliquer pour découvrir un refus.
     *
     * @return list<string>
     */
    public function blockers(OrderDraft $draft): array
    {
        $blockers = [];

        if ($draft->items()->count() === 0) {
            $blockers[] = 'Aucun service n’a été choisi.';
        }

        if (blank($draft->address)) {
            $blockers[] = 'L’adresse de l’intervention est nécessaire pour envoyer un professionnel.';
        }

        /*
         * PAS DE ZONE, PAS DE COMMANDE — et on le dit AVANT le clic.
         *
         * Une réservation sans zone est une réservation que le dispatch ne peut pas servir : il ne
         * saurait ni quel tarif appliquer, ni quels prestataires interroger. Elle serait confirmée,
         * payée, puis abandonnée faute de candidat — et c'est le client qui découvrirait le
         * problème, plusieurs minutes après avoir décidé.
         */
        if ($draft->items()->count() > 0 && ! $draft->service_zone_id) {
            $blockers[] = filled($draft->address)
                ? 'Nous n’intervenons pas encore à cette adresse. Essayez une autre adresse, ou laissez-nous vos coordonnées pour être prévenu de l’ouverture.'
                : 'La zone d’intervention n’a pas pu être déterminée à partir de votre adresse.';
        }

        /*
         * Le métier doit être OUVERT dans cette zone. Le catalogue décide zone par zone : un métier
         * actif ailleurs mais fermé ici ne doit pas franchir la confirmation, sans quoi la
         * recherche partirait pour un service qu'on ne vend pas à cette adresse.
         */
        $zoneId = $draft->service_zone_id ? (int) $draft->service_zone_id : null;

        if ($zoneId) {
            $resolver = app(ZonePricingResolver::class);

            foreach ($draft->items()->with('trade')->get() as $item) {
                if (! $item->trade) {
                    continue;
                }

                if (! $resolver->isOpen((int) $item->trade_id, $zoneId)) {
                    $blockers[] = sprintf(
                        'Le service « %s » n’est pas encore disponible dans cette zone.',
                        $item->trade->name,
                    );
                }
            }
        }

        return $blockers;
    }

    /**
     * Le paiement peut-il être pré-autorisé ?
     *
     * L'autorisation Stripe exige un prestataire ASSIGNÉ et raccordé à Connect : la charge est une
     * « destination charge » vers son compte. Avec l'attribution automatique, personne n'est
     * désigné à la confirmation — le paiement attend donc l'assignation.
     *
     * Ce n'est pas une limitation contournable : sans destination, Stripe n'a nulle part où
     * envoyer l'argent.
     *
     * @return array{ready: bool, reason: string|null}
     */
    public function paymentReadiness(Booking $booking): array
    {
        $provider = $booking->employe;

        if (! $provider) {
            return [
                'ready' => false,
                'reason' => 'Le paiement sera pré-autorisé dès qu’un professionnel aura accepté votre demande.',
            ];
        }

        if (! $provider->canReceiveStripeConnectPayments()) {
            return [
                'ready' => false,
                'reason' => 'Ce professionnel n’a pas encore terminé la configuration de ses paiements.',
            ];
        }

        return ['ready' => true, 'reason' => null];
    }

    /**
     * Pré-autorise le paiement, ou refuse en disant pourquoi.
     *
     * Aucun soft-fail ici : une réservation confirmée sans autorisation valide est une
     * intervention qu'on enverra faire sans garantie d'être payé. Mieux vaut un refus lisible.
     *
     * @throws ValidationException
     */
    public function authorizePayment(Booking $booking, string $paymentMethodId, string $plan = PaymentPlan::FULL): Booking
    {
        // Déjà autorisée : rejouer créerait une seconde empreinte sur la carte du client.
        if ($booking->payment_status === 'authorized' && $booking->stripe_payment_intent_id) {
            return $booking;
        }

        $readiness = $this->paymentReadiness($booking);

        if (! $readiness['ready']) {
            throw ValidationException::withMessages(['payment' => [$readiness['reason']]]);
        }

        /*
         * Une formule inconnue retombe sur la retenue intégrale plutôt que d'échouer : la valeur
         * vient du navigateur, et refuser un paiement à cause d'une chaîne inattendue coûterait
         * une commande là où le défaut est parfaitement acceptable.
         */
        if ($plan === PaymentPlan::DEPOSIT) {
            return app(OrderPaymentPlanner::class)->authorizeWithDeposit($booking, $paymentMethodId);
        }

        app(MissionPaymentService::class)->authorize($booking, $paymentMethodId);

        return $booking->fresh()->refresh();
    }

    /**
     * Les formules de règlement ouvertes à cette réservation.
     *
     * @return list<array{plan: string, label: string, due_now_cents: int, held_cents: int, detail: string}>
     */
    public function paymentOptions(Booking $booking): array
    {
        return app(OrderPaymentPlanner::class)->optionsFor($booking);
    }

    /** @throws ValidationException */
    protected function assertConfirmable(OrderDraft $draft): void
    {
        $blockers = $this->blockers($draft);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['confirmation' => $blockers]);
        }
    }

    /**
     * Une réservation par métier — même en multi-services.
     *
     * Chaque ligne peut partir chez un prestataire différent et se suivre séparément, alors que le
     * client ne voit qu'une commande. Une réservation unique portant trois métiers rendrait
     * impossible d'en assigner un sans les autres.
     */
    protected function createBooking(
        OrderDraft $draft,
        User $client,
        OrderDraftItem $item,
        PriceBreakdown $quote,
    ): Booking {
        $scheduledAt = $item->scheduled_at ?? $draft->scheduled_at;

        $booking = Booking::create(array_filter([
            'booking_reference' => $this->uniqueReference(),
            'client_id' => $client->id,
            /*
             * Le professionnel choisi par le client suit jusqu'ici. Le statut reste « en attente » :
             * il lui est PROPOSÉ, il n'a pas encore accepté. Sans ce report, un client qui a
             * délibérément choisi son prestataire verrait sa commande repartir en attribution
             * automatique — et le paiement attendrait une assignation déjà faite.
             */
            'employe_id' => $item->provider_id,
            'status' => BookingStatus::EN_ATTENTE,
            'address' => $draft->address,
            'destination_lat' => $draft->lat,
            'destination_lng' => $draft->lng,
            /*
             * LA GÉOGRAPHIE ET LE MÉTIER SUIVENT LA RÉSERVATION.
             *
             * C'est le maillon qui manquait : la réservation naissait sans zone ni métier, et le
             * dispatch devait les redeviner — ou renoncer à filtrer. Avec ces trois colonnes, la
             * requête candidate peut imposer « ce métier, cette zone » dans le SQL lui-même.
             */
            'trade_id' => $item->trade_id,
            'service_zone_id' => $draft->service_zone_id,
            'postal_code' => $draft->postal_code,
            // Le mode voyage aussi : c'est lui qui décide entre la chaîne d'offres immédiate et
            // l'offre planifiée à long délai. Sans lui, tout devenait « planifié ».
            'booking_mode' => $draft->mode === OrderMode::ASAP ? 'asap' : 'scheduled',
            'scheduled_at' => $scheduledAt,
            'scheduled_date' => $scheduledAt?->toDateString(),
            'scheduled_time' => $scheduledAt?->format('H:i:s'),
            'date' => $scheduledAt?->toDateString(),
            'heure' => $scheduledAt?->format('H:i:s'),
            'commentaire_client' => $draft->client_notes,
            // Le montant retenu est le PLANCHER de la fourchette : on n'engage jamais le client
            // sur le haut d'une estimation qu'il n'a pas validée. L'écart éventuel se règle à la
            // clôture, sur constat.
            'estimated_price' => $quote->quoteOnly ? null : $quote->minCents / 100,
            'devis_estime' => $quote->quoteOnly ? null : $quote->minCents / 100,
            /*
             * L'instantané du devis voyage avec la réservation : les libellés, les montants et la
             * révision du questionnaire employée. C'est ce qui rend la facture explicable ligne
             * par ligne, six mois plus tard, sans dépendre d'un catalogue qui aura changé.
             */
            'pricing_snapshot' => [
                'currency' => $draft->currency ?? 'EUR',
                'min_cents' => $quote->minCents,
                'max_cents' => $quote->maxCents,
                'quote_only' => $quote->quoteOnly,
                'lines' => $quote->lines,
                'trade_form_revision_id' => $item->trade_form_revision_id,
                'order_draft_reference' => $draft->reference,
                'mode' => $draft->mode,
            ],
            'trade_form_answers' => $item->answers
                ->map(fn ($answer) => [
                    'code' => $answer->question_code,
                    'question' => $answer->question_label_snapshot,
                    'answer' => $answer->answer_label_snapshot,
                    'price_impact_cents' => $answer->price_impact_cents,
                ])
                ->all(),
        ], fn ($value) => $value !== null));

        $item->update([
            'status' => OrderDraftStatus::CONVERTED,
            'metadata' => array_merge($item->metadata ?? [], ['booking_id' => $booking->id]),
        ]);

        return $booking;
    }

    /** Référence lisible et unique — elle se dicte au téléphone au support. */
    protected function uniqueReference(): string
    {
        foreach (range(1, 10) as $ignored) {
            $reference = HumanReference::prefixed('CUX-', 6);

            if (! Booking::where('booking_reference', $reference)->exists()) {
                return $reference;
            }
        }

        return HumanReference::prefixed('CUX-', 10);
    }
}
