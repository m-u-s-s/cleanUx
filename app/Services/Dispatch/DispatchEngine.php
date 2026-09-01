<?php

namespace App\Services\Dispatch;

use App\Jobs\Dispatch\EscalateMissionAssignmentJob;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrderDraftItem;
use App\Models\User;
use App\Notifications\Dispatch\MissionOfferNotification;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Organizations\OrganizationNotifier;
use App\Services\Presence\ProviderPresenceService;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** LE MOTEUR DE RÉPARTITION — une seule porte, pour tous les rôles et les deux modes. */
class DispatchEngine
{
    public function __construct(
        protected CandidateFinder $candidates,
        protected OfferTransmitter $transmitter,
    ) {}

    // ─── Ouverture ───────────────────────────────────────────────────────────────────────────

    /** Une réservation confirmée entre dans le moteur. */
    public function dispatchBooking(Booking $booking, ?OrderDraftItem $item = null): ?MissionAssignment
    {
        if (($booking->booking_mode ?? null) === 'asap') {
            $search = $this->openImmediate($booking, $item);

            return $search
                ? $this->currentOffer($search->mission_id)
                : null;
        }

        return $this->openScheduled($booking);
    }

    /** Ouvre — ou retrouve — la recherche d'une intervention immédiate. */
    public function openImmediate(Booking $booking, ?OrderDraftItem $item = null): ?AsapDispatchRequest
    {
        $existing = AsapDispatchRequest::query()
            ->where('booking_id', $booking->id)
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        $mission = $this->ensureMission($booking);
        $tradeId = $this->candidates->tradeIdFor($booking);

        if (! $mission || ! $tradeId) {
            Log::warning('DispatchEngine: intervention immédiate sans mission ni métier', [
                'booking_id' => $booking->id,
                'mission' => $mission?->id,
                'trade_id' => $tradeId,
            ]);

            return null;
        }

        $this->previenirLaSocieteChoisie($booking);

        $search = AsapDispatchRequest::create([
            'booking_id' => $booking->id,
            'mission_id' => $mission->id,
            'order_draft_id' => $item?->order_draft_id,
            'order_draft_item_id' => $item?->id,
            'trade_id' => $tradeId,
            'status' => AsapStatus::SEARCHING,
            'lat' => $booking->destination_lat,
            'lng' => $booking->destination_lng,
            'radius_m' => (int) Config::get('dispatch.waves.initial_radius_m', 5000),
            'wave' => 1,
            'searching_at' => now(),
            'deadline_at' => now()->addSeconds((int) Config::get('dispatch.search_deadline_seconds', 300)),
        ]);

        $this->offerNext($search);

        return $search->fresh();
    }

    /** Le rendez-vous planifié devient une OFFRE d'abord — le repli d'office reste sa garantie. */
    public function openScheduled(Booking $booking): ?MissionAssignment
    {
        $mission = $this->ensureMission($booking);

        if (! $mission) {
            return null;
        }

        return $this->next($mission);
    }

    // ─── Progression de la chaîne ────────────────────────────────────────────────────────────

    /** L'offre suivante pour cette mission — quel que soit le mode. */
    public function next(Mission $mission, bool $imposerSiEpuise = true): ?MissionAssignment
    {
        $search = AsapDispatchRequest::query()
            ->where('mission_id', $mission->id)
            ->open()
            ->first();

        if ($search) {
            return $this->offerNext($search);
        }

        // UNE RÉSERVATION IMMÉDIATE SANS RECHERCHE OUVERTE EN OUVRE UNE — elle ne bascule pas sur le planifié.
        $booking = $mission->booking;

        if ($booking && ($booking->booking_mode ?? null) === 'asap') {
            $ouverte = $this->openImmediate($booking);

            return $ouverte ? $this->currentOffer($ouverte->mission_id) : null;
        }

        return $this->offerScheduled($mission, $imposerSiEpuise);
    }

    /** La suite d'une recherche immédiate : offre, élargissement, broadcast, ou épuisement. */
    public function offerNext(AsapDispatchRequest $search): ?MissionAssignment
    {
        $search->refresh();

        if ($search->status !== AsapStatus::SEARCHING) {
            return null;
        }

        $mission = $search->mission;
        $booking = $search->booking;

        if (! $mission || ! $booking) {
            return null;
        }

        // La mission est déjà partie : la recherche n'a plus lieu d'être.
        if ($mission->lead_provider_user_id || $mission->status !== 'planned') {
            return null;
        }

        // UNE SEULE OFFRE VIVANTE À LA FOIS, hors broadcast.
        if (! $search->broadcast_at && $this->currentOffer($mission->id)) {
            return null;
        }

        $echeance = $search->deadline_at;

        if ($echeance !== null && $echeance->isPast()) {
            $this->exhaust($search);

            return null;
        }

        $maxRadius = (int) Config::get('dispatch.waves.max_radius_m', 20000);
        $step = (int) Config::get('dispatch.waves.step_m', 5000);
        $tried = $this->alreadyAsked($mission);

        $candidates = collect();

        // Bornée : chaque tour élargit d'un palier, et le rayon maximal arrête la boucle. Un
        // `while (true)` ici bloquerait une requête web sur une recherche sans candidat.
        for ($wave = 0; $wave <= 12; $wave++) {
            $candidates = $this->candidates->immediate($booking, (int) $search->radius_m, $tried);

            if ($candidates->isNotEmpty() || $search->radius_m >= $maxRadius) {
                break;
            }

            $search->update([
                'radius_m' => min((int) $search->radius_m + $step, $maxRadius),
                'wave' => (int) $search->wave + 1,
                'expansion_count' => (int) $search->expansion_count + 1,
            ]);

            Log::info('DispatchEngine: vague élargie', [
                'search_id' => $search->id,
                'radius_m' => $search->radius_m,
                'wave' => $search->wave,
            ]);
        }

        if ($candidates->isEmpty()) {
            $this->exhaust($search);

            return null;
        }

        // DERNIÈRE VAGUE : le rayon ne peut plus grandir, on cesse d'attendre chacun à son tour.
        if ((int) $search->radius_m >= $maxRadius && $search->broadcast_at === null) {
            return $this->broadcast($search, $mission, $candidates);
        }

        $premier = $candidates->all()[0] ?? null;

        if ($premier === null) {
            return null;
        }

        return $this->createOffer(
            $mission,
            $premier->user,
            $this->immediateTimeout($booking),
            $premier->distanceM,
            $search,
        );
    }

    /** La suite d'une chaîne planifiée : le meilleur candidat non encore tenté. */
    public function offerScheduled(Mission $mission, bool $imposerSiEpuise = true): ?MissionAssignment
    {
        $booking = $mission->booking;

        if (! $booking || $mission->lead_provider_user_id || $mission->status !== 'planned') {
            return null;
        }

        if ($this->currentOffer($mission->id)) {
            return null;
        }

        $tried = $this->alreadyAsked($mission);
        $depth = count($tried);

        if ($depth >= (int) Config::get('dispatch.max_escalation_depth', 5)) {
            return $imposerSiEpuise ? $this->assignByDefault($mission, $booking) : null;
        }

        $candidates = $this->candidates->scheduled($booking, $tried);

        if ($candidates->isEmpty()) {
            return $imposerSiEpuise ? $this->assignByDefault($mission, $booking) : null;
        }

        $premier = $candidates->all()[0] ?? null;

        if ($premier === null) {
            return $imposerSiEpuise ? $this->assignByDefault($mission, $booking) : null;
        }

        return $this->createOffer(
            $mission,
            $premier->user,
            (int) Config::get('dispatch.scheduled_offer_timeout_seconds', 1800),
            $premier->distanceM,
            null,
        );
    }

    /**
     * Impose la mission au meilleur candidat, sans offre prealable. Acte DISTINCT de la recherche :
     * il ne se declenche pas d'un epuisement, il se decide.
     */
    public function imposerDOffice(Mission $mission): ?MissionAssignment
    {
        $booking = $mission->booking;

        if (! $booking || $mission->lead_provider_user_id || $mission->status !== 'planned') {
            return null;
        }

        // LE MODE IMMEDIAT A SA PROPRE SORTIE et son propre selecteur de candidats. Contraindre
        // ici designerait un prestataire hors ligne, la recherche restant ouverte cote client.
        if (($booking->booking_mode ?? null) === 'asap') {
            return null;
        }

        // UNE OFFRE VIVANTE PASSE AVANT LA CONTRAINTE : sans cette garde, son destinataire
        // accepterait une mission dont le lead est deja quelqu'un d'autre.
        if ($this->currentOffer($mission->id)) {
            return null;
        }

        return $this->assignByDefault($mission, $booking);
    }

    // ─── Résolutions ─────────────────────────────────────────────────────────────────────────

    /** Une offre a été acceptée : la recherche se ferme et les autres modales aussi. */
    public function onAccepted(MissionAssignment $accepted): void
    {
        $missionId = (int) $accepted->mission_id;

        // LE PRESTATAIRE DEVIENT OCCUPÉ DÈS L'ACCEPTATION, pas au démarrage de l'intervention.
        try {
            $prestataire = $accepted->user;

            if ($prestataire) {
                app(ProviderPresenceService::class)->goBusy($prestataire);
            }
        } catch (\Throwable $e) {
            Log::warning('DispatchEngine: bascule en occupé impossible', [
                'assignment_id' => $accepted->id,
                'error' => $e->getMessage(),
            ]);
        }

        MissionAssignment::query()
            ->where('mission_id', $missionId)
            ->where('id', '!=', $accepted->id)
            ->whereIn('assignment_status', ['cancelled', 'assigned'])
            ->get()
            ->each(fn (MissionAssignment $other) => $this->transmitter->withdraw($other, 'taken'));

        AsapDispatchRequest::query()
            ->where('mission_id', $missionId)
            ->open()
            ->get()
            ->each(function (AsapDispatchRequest $search) use ($accepted) {
                $search->update([
                    'status' => AsapStatus::ACCEPTED,
                    'accepted_by_user_id' => $accepted->user_id,
                    'accepted_at' => now(),
                    'free_cancellation_until' => now()->addMinutes(
                        (int) Config::get('order_engine.asap_free_cancellation_minutes', 3)
                    ),
                ]);
            });
    }

    /** L'offre n'est plus à prendre : la modale se ferme d'elle-même. */
    public function withdraw(MissionAssignment $assignment, string $reason = 'taken'): void
    {
        $this->transmitter->withdraw($assignment, $reason);
    }

    /** LE CLIENT A CHOISI UNE SOCIÉTÉ : ses répartiteurs sont prévenus. */
    protected function previenirLaSocieteChoisie(Booking $booking): void
    {
        $societeId = $booking->assigned_provider_organization_id;

        if (! $societeId) {
            return;
        }

        $metier = $booking->resolveTrade();

        try {
            app(OrganizationNotifier::class)->notifierPorteursDe(
                organisationId: (int) $societeId,
                permission: 'missions.dispatch',
                titre: 'Nouvelle demande immédiate',
                corps: sprintf(
                    '%s — %s. Un de vos collaborateurs doit accepter avant l’échéance.',
                    $metier === null ? 'Intervention' : (string) $metier->name,
                    trim(($booking->postal_code ?? '').' '.($booking->city ?? '')) ?: 'adresse à confirmer',
                ),
                donnees: [
                    'type' => 'company_immediate_request',
                    'booking_id' => (int) $booking->id,
                ],
                cleIdempotence: 'company_immediate:'.$booking->id,
            );
        } catch (\Throwable $e) {
            Log::warning('DispatchEngine: société non prévenue de la demande immédiate', [
                'booking_id' => $booking->id,
                'organisation' => $societeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Personne n'a répondu : la recherche s'arrête, le client choisit sa suite (lot 6). */
    public function exhaust(AsapDispatchRequest $search): AsapDispatchRequest
    {
        if ($search->status === AsapStatus::SEARCHING) {
            $search->update([
                'status' => AsapStatus::EXPIRED,
                'metadata' => array_merge($search->metadata ?? [], [
                    'exhausted_at' => now()->toIso8601String(),
                    'last_radius_m' => (int) $search->radius_m,
                    'waves' => (int) $search->wave,
                ]),
            ]);

            Log::info('DispatchEngine: recherche épuisée sans acceptation', [
                'search_id' => $search->id,
                'booking_id' => $search->booking_id,
                'radius_m' => $search->radius_m,
            ]);
        }

        return $search->fresh();
    }

    /** Relance une recherche épuisée — « continuer à attendre ». */
    public function relaunch(AsapDispatchRequest $search): AsapDispatchRequest
    {
        $search->update([
            'status' => AsapStatus::SEARCHING,
            'searching_at' => now(),
            'broadcast_at' => null,
            'deadline_at' => now()->addSeconds((int) Config::get('dispatch.search_deadline_seconds', 300)),
        ]);

        $this->offerNext($search->fresh());

        return $search->fresh();
    }

    // ─── Fabrication d'une offre ─────────────────────────────────────────────────────────────

    /** Le prestataire est-il en règle côté contrôle facial pour CETTE mission ? */
    protected function passeLeControleFacial(User $provider, Mission $mission): bool
    {
        $booking = $mission->booking;

        $verdict = $booking !== null
            ? app(FaceCheckGate::class)->inspectForBooking($provider, $booking)
            : app(FaceCheckGate::class)->inspectProvider($provider);

        return $verdict->allowed();
    }

    /** Une offre, une ligne, trois canaux. */
    public function createOffer(
        Mission $mission,
        User $provider,
        int $ttlSeconds,
        ?int $distanceM = null,
        ?AsapDispatchRequest $search = null,
    ): ?MissionAssignment {
        if (! $provider->hasClearedKyc()) {
            return null;
        }

        // LE CONTRÔLE FACIAL, AU MÊME ENDROIT QUE LE KYC ET POUR LA MÊME RAISON.
        if (! $this->passeLeControleFacial($provider, $mission)) {
            return null;
        }

        $assignment = DB::transaction(function () use ($mission, $provider, $ttlSeconds) {
            $now = now();

            // `updateOrCreate` et non `create` : `mission_assignments` porte un index unique (mission, prestataire).
            return MissionAssignment::updateOrCreate(
                ['mission_id' => $mission->id, 'user_id' => $provider->id],
                [
                    'role_on_mission' => 'lead',
                    'assignment_status' => 'assigned',
                    'assigned_at' => $now,
                    'notification_sent_at' => $now,
                    'expires_at' => $now->copy()->addSeconds($ttlSeconds),
                    'declined_at' => null,
                    'decline_reason' => null,
                ],
            );
        });

        try {
            $provider->notify(new MissionOfferNotification($mission, $assignment));
        } catch (\Throwable $e) {
            Log::warning('DispatchEngine: notification base impossible', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->transmitter->transmit($assignment->fresh(['mission', 'user']), $distanceM);

        // Le compte à rebours est tenu par le serveur : le job arrive à l'échéance, que
        // l'application soit ouverte, fermée ou éteinte.
        EscalateMissionAssignmentJob::dispatch($assignment->id)->delay($assignment->expires_at);

        if ($search) {
            $search->increment('notified_count');
            $this->traceNotification($search, $provider, $distanceM);
        }

        Log::info('DispatchEngine: offre émise', [
            'assignment_id' => $assignment->id,
            'mission_id' => $mission->id,
            'provider_id' => $provider->id,
            'distance_m' => $distanceM,
            'expires_at' => $assignment->expires_at?->toIso8601String(),
        ]);

        return $assignment;
    }

    /**
     * La dernière vague : tout le monde en même temps, premier qui accepte gagne.
     *
     * @param  Collection<int, DispatchCandidate>  $candidates
     */
    protected function broadcast(AsapDispatchRequest $search, Mission $mission, Collection $candidates): ?MissionAssignment
    {
        $limit = (int) Config::get('dispatch.broadcast_max_candidates', 20);
        $ttl = $this->immediateTimeout($search->booking);

        $search->update(['broadcast_at' => now()]);

        $first = null;

        foreach ($candidates->take($limit) as $candidat) {
            $assignment = $this->createOffer($mission, $candidat->user, $ttl, $candidat->distanceM, $search);

            $first ??= $assignment;
        }

        Log::info('DispatchEngine: dernière vague diffusée', [
            'search_id' => $search->id,
            'candidates' => min($candidates->count(), $limit),
        ]);

        return $first;
    }

    // ─── Outils ──────────────────────────────────────────────────────────────────────────────

    /** Le TTL d'une offre immédiate, surchargeable par métier (un toiturier lit plus lentement). */
    public function immediateTimeout(?Booking $booking): int
    {
        $metier = $booking?->resolveTrade();
        $slug = $metier === null ? '' : (string) $metier->slug;

        return (int) Config::get(
            "dispatch.timeout_per_trade.{$slug}",
            Config::get('dispatch.default_timeout', 20)
        );
    }

    /**
     * QUI A DÉJÀ ÉTÉ SOLLICITÉ — et « annulée » ne compte pas.
     *
     * @return list<int>
     */
    protected function alreadyAsked(Mission $mission): array
    {
        return $mission->assignments()
            ->whereIn('assignment_status', ['assigned', 'declined', 'expired', 'accepted'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** L'offre encore vivante sur cette mission, s'il y en a une. */
    public function currentOffer(?int $missionId): ?MissionAssignment
    {
        if (! $missionId) {
            return null;
        }

        return MissionAssignment::query()
            ->where('mission_id', $missionId)
            ->where('assignment_status', 'assigned')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    /** La mission d'exécution existe AVANT la première offre. */
    public function ensureMission(Booking $booking): ?Mission
    {
        $existing = $booking->resolveMission();

        if ($existing) {
            return $existing;
        }

        try {
            return app(MissionLifecycleService::class)->syncFromRendezVous($booking);
        } catch (\Throwable $e) {
            Log::error('DispatchEngine: création de mission impossible', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Le repli du planifié : personne n'a accepté, on assigne quand même. */
    protected function assignByDefault(Mission $mission, Booking $booking): ?MissionAssignment
    {
        $tried = $mission->assignments()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        // Les refus explicites restent exclus : forcer une mission chez quelqu'un qui vient de la
        // refuser produit une annulation le jour même, pas une intervention.
        $declined = $mission->assignments()
            ->whereIn('assignment_status', ['declined'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidates = $this->candidates->scheduled($booking, $declined);

        $best = $candidates->all()[0] ?? null;

        if ($best === null) {
            Log::info('DispatchEngine: planifié sans candidat, aucune assignation d’office', [
                'mission_id' => $mission->id,
                'tried' => $tried,
            ]);

            return null;
        }

        $assignment = MissionAssignment::updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $best->user->id],
            [
                'role_on_mission' => 'lead',
                'assignment_status' => 'accepted',
                'assigned_at' => now(),
                'accepted_at' => now(),
            ],
        );

        $mission->update([
            'status' => 'assigned',
            'lead_provider_user_id' => $best->user->id,
            'lead_employee_id' => $best->user->id,
        ]);

        $booking->update([
            'employe_id' => $best->user->id,
            'matched_at' => now(),
            'status' => $booking->status === BookingStatus::EN_ATTENTE ? BookingStatus::CONFIRME : $booking->status,
        ]);

        Log::info('DispatchEngine: assignation d’office (repli planifié)', [
            'mission_id' => $mission->id,
            'provider_id' => $best->user->id,
        ]);

        return $assignment;
    }

    /** La trace de « qui a été prévenu, quand, à quelle distance ». */
    protected function traceNotification(AsapDispatchRequest $search, User $provider, ?int $distanceM): void
    {
        try {
            DB::table('asap_dispatch_notifications')->insert([
                'asap_dispatch_request_id' => $search->id,
                'user_id' => $provider->id,
                'distance_m' => $distanceM,
                'radius_m' => (int) $search->radius_m,
                'notified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Déjà prévenu pour cette recherche : rien à faire.
        }
    }
}
