<?php

namespace App\Services\OrderEngine;

use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\International\CountryMarketResolver;
use App\Services\Payments\MissionPaymentService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PaymentPlan;
use App\Support\Domain\TradeRouteRules;
use App\Support\HumanReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** La confirmation : le panier devient une réservation. */
class OrderConfirmationService
{
    public function __construct(
        protected OrderDraftManager $drafts,
        protected BundleComposer $bundles,
        protected DispatchEngine $dispatch,
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

        // DERNIÈRE CHANCE DE RÉSOUDRE LA ZONE — avant de refuser.
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
                $bookings[] = [
                    'booking' => $this->createBooking($locked, $client, $line['item'], $line['quote']),
                    'item' => $line['item'],
                ];
            }

            $locked->update([
                'client_id' => $client->id,
                'status' => OrderDraftStatus::CONVERTED,
                'converted_at' => now(),
                'converted_booking_id' => $bookings[0]['booking']->id ?? null,
                // Le devis est FIGÉ : c'est le prix accepté qui engage, pas celui qu'un
                // recalcul donnerait demain.
                'estimate_min_cents' => $quote['order']->minCents,
                'estimate_max_cents' => $quote['order']->maxCents,
                'total_cents' => $quote['order']->minCents,
            ]);

            // LA PORTE AMONT UNIQUE DU DISPATCH.
            foreach ($bookings as $ligne) {
                $this->dispatch->dispatchBooking($ligne['booking']->fresh(), $ligne['item']);
            }

            return $locked->fresh();
        });
    }

    /**
     * La société et le local à porter sur la réservation, si le panier en désigne un.
     *
     * @return array<string, int>
     */
    protected function contexteSociete(OrderDraft $draft, User $client): array
    {
        $metadonnees = (array) $draft->metadata;
        $orgId = (int) ($metadonnees['organization_account_id'] ?? 0);
        $siteId = (int) ($metadonnees['organization_site_id'] ?? 0);

        if ($orgId <= 0) {
            return [];
        }

        // L'auteur est-il TOUJOURS membre actif de cette société ?
        $membre = OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('user_id', $client->id)
            ->where('status', 'active')
            ->exists();

        if (! $membre) {
            return [];
        }

        $contexte = ['customer_organization_id' => $orgId];

        // Le local doit appartenir à CETTE société : un identifiant venu de la barre d'adresse ne
        // doit pas rattacher la commande au bureau d'une autre entreprise.
        if ($siteId > 0 && OrganizationSite::query()
            ->where('organization_account_id', $orgId)
            ->whereKey($siteId)
            ->exists()) {
            $contexte['organization_site_id'] = $siteId;
        }

        return $contexte;
    }

    /**
     * Ce qui manque encore pour confirmer.
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

        // PAS DE ZONE, PAS DE COMMANDE — et on le dit AVANT le clic.
        if ($draft->items()->count() > 0 && ! $draft->service_zone_id) {
            $blockers[] = filled($draft->address)
                ? 'Nous n’intervenons pas encore à cette adresse. Essayez une autre adresse, ou laissez-nous vos coordonnées pour être prévenu de l’ouverture.'
                : 'La zone d’intervention n’a pas pu être déterminée à partir de votre adresse.';
        }

        // Le métier doit être OUVERT dans cette zone.
        $zoneId = $draft->service_zone_id ? (int) $draft->service_zone_id : null;
        $resolver = app(ZonePricingResolver::class);

        foreach ($draft->items()->with(['trade.questions', 'trade.translations'])->get() as $item) {
            if (! $item->trade) {
                continue;
            }

            if ($zoneId && ! $resolver->isOpen((int) $item->trade_id, $zoneId)) {
                $blockers[] = sprintf(
                    'Le service « %s » n’est pas encore disponible dans cette zone.',
                    $item->trade->translate('name'),
                );
            }

            // UN TRAJET SANS POINT D'ARRIVÉE N'EST PAS UNE COMMANDE SERVABLE.
            if (TradeRouteRules::estUnTrajet($item->trade)
                && ($draft->dropoff_lat === null || $draft->dropoff_lng === null)) {
                $blockers[] = sprintf(
                    'Indiquez le point d’arrivée pour « %s » : nous ne pouvons pas envoyer quelqu’un sans savoir où aller.',
                    $item->trade->translate('name'),
                );
            }
        }

        return $blockers;
    }

    /**
     * Le paiement peut-il être pré-autorisé ?
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

        // Une formule inconnue retombe sur la retenue intégrale plutôt que d'échouer : la valeur vient du navigateur, et refuser un paiement à cause d'une chaîne inattendue coûterait une commande là où le défaut est parfaitement acceptable.
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

    /** Une réservation par métier — même en multi-services. */
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
            // Le professionnel choisi par le client suit jusqu'ici.
            'employe_id' => $item->provider_id,
            'status' => BookingStatus::EN_ATTENTE,
            // LA DEVISE SUIT LA POSITION — ET LE PARCOURS WEB NE LA POSAIT PAS DU TOUT.
            'currency' => app(CountryMarketResolver::class)->deviseAttendue(
                client: $client,
                zone: $draft->service_zone_id ? ServiceZone::find($draft->service_zone_id) : null,
            ),
            'address' => $draft->address,
            'destination_lat' => $draft->lat,
            'destination_lng' => $draft->lng,
            // LE POINT D'ARRIVÉE SUIT LA RÉSERVATION — et c'est LUI qui en fait une course.
            'dropoff_address' => $draft->dropoff_address,
            'dropoff_lat' => $draft->dropoff_lat,
            'dropoff_lng' => $draft->dropoff_lng,
            'dropoff_postal_code' => $draft->dropoff_postal_code,
            'route_distance_m' => $draft->route_distance_m,
            'route_duration_s' => $draft->route_duration_s,
            'route_source' => $draft->route_source,
            // LA GÉOGRAPHIE ET LE MÉTIER SUIVENT LA RÉSERVATION.
            'trade_id' => $item->trade_id,
            'service_zone_id' => $draft->service_zone_id,
            'postal_code' => $draft->postal_code,
            // LE CONTEXTE SOCIÉTÉ, REVÉRIFIÉ ICI ET PAS SEULEMENT À L'ENTRÉE.
            ...$this->contexteSociete($draft, $client),
            // Le mode voyage aussi : c'est lui qui décide entre la chaîne d'offres immédiate et
            // l'offre planifiée à long délai. Sans lui, tout devenait « planifié ».
            'booking_mode' => $draft->mode === OrderMode::ASAP ? 'asap' : 'scheduled',
            'scheduled_at' => $scheduledAt,
            'scheduled_date' => $scheduledAt?->toDateString(),
            'scheduled_time' => $scheduledAt?->format('H:i:s'),
            'date' => $scheduledAt?->toDateString(),
            'heure' => $scheduledAt?->format('H:i:s'),
            'commentaire_client' => $draft->client_notes,
            // LE BÉNÉFICIAIRE ET LE LIEU SUIVENT LA RÉSERVATION (E1, E2).
            'beneficiary_name' => $draft->beneficiary_name,
            'beneficiary_phone' => $draft->beneficiary_phone,
            'beneficiary_note' => $draft->beneficiary_note,
            'client_place_id' => $draft->client_place_id,
            // Le montant retenu est le PLANCHER de la fourchette : on n'engage jamais le client
            // sur le haut d'une estimation qu'il n'a pas validée. L'écart éventuel se règle à la
            // clôture, sur constat.
            'estimated_price' => $quote->quoteOnly ? null : $quote->minCents / 100,
            'devis_estime' => $quote->quoteOnly ? null : $quote->minCents / 100,
            // LA DURÉE ESTIMÉE VOYAGE AVEC LE PRIX — elle ne le faisait pas.
            'duree_estimee' => $quote->durationMin > 0 ? $quote->durationMin : null,
            'estimated_duration_minutes' => $quote->durationMin > 0 ? $quote->durationMin : null,
            // LE TEMPS ACHETE SUIT LA RESERVATION, et c'est lui qui engage.
            'purchased_minutes' => $item->purchased_minutes,
            // L'instantané du devis voyage avec la réservation : les libellés, les montants et la révision du questionnaire employée.
            'pricing_snapshot' => [
                // LE NOM DU MÉTIER, FIGÉ AVEC LE RESTE.
                'service_name' => $item->trade?->name,
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
