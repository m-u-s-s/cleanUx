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

/**
 * LE MOTEUR DE RÉPARTITION — une seule porte, pour tous les rôles et les deux modes.
 *
 * Il y avait deux chemins qui ne se parlaient pas : `MissionDispatchService` proposait la mission à
 * UN prestataire à la fois avec un compte à rebours, `AsapDispatchService` prévenait TOUT LE MONDE
 * dans un rayon qui s'élargissait. Chacun tenait la moitié du patron Uber, aucun ne tenait l'autre,
 * et une même course pouvait sortir par les deux.
 *
 * CE QUE FAIT CE MOTEUR, dans l'ordre, pour une intervention IMMÉDIATE :
 *
 *   1. Vague 1 — les candidats du `CandidateFinder` dans le rayon initial, du plus proche au plus
 *      lointain. Offre SÉQUENTIELLE au premier, TTL 20 s (`config/dispatch.php`, surchargeable par
 *      métier). Refus ou silence → le suivant, en excluant ceux déjà tentés.
 *   2. Vague épuisée → le rayon s'élargit d'un palier, et on recommence. Le client attend : mieux
 *      vaut un professionnel un peu plus loin que pas de professionnel.
 *   3. Rayon maximal atteint → BROADCAST, premier qui accepte gagne. C'est le seul moment où
 *      plusieurs modales s'ouvrent en même temps, et le verrou pessimiste de l'acceptation est ce
 *      qui empêche deux personnes de partir pour la même course.
 *   4. Échéance globale sans acceptation → la recherche s'arrête et le client choisit sa suite
 *      (attendre encore, basculer en rendez-vous, annuler). Jamais un cul-de-sac.
 *
 * POUR UN RENDEZ-VOUS PLANIFIÉ, le même moteur avec deux réglages : le score prime sur la distance
 * (personne n'attend derrière la porte), et le TTL se compte en minutes. À l'épuisement de la
 * chaîne, l'assignation d'office reprend la main — le comportement historique devient le REPLI, et
 * non plus la règle : un prestataire à qui l'on n'a rien demandé découvre sa mission la veille.
 *
 * CHAQUE ÉVÉNEMENT LAISSE UNE LIGNE. Offre, refus, expiration, élargissement, broadcast : c'est ce
 * qui permet à l'administration de rejouer l'histoire d'une recherche, et c'est aussi la seule
 * façon d'avoir un taux d'acceptation qui veut dire quelque chose.
 */
class DispatchEngine
{
    public function __construct(
        protected CandidateFinder $candidates,
        protected OfferTransmitter $transmitter,
    ) {}

    // ─── Ouverture ───────────────────────────────────────────────────────────────────────────

    /**
     * Une réservation confirmée entre dans le moteur.
     *
     * C'est LA porte amont : le moteur de commande appelle ceci et rien d'autre. Idempotent — une
     * confirmation rejouée ne lance pas deux recherches, ce qui doublerait les offres et pourrait
     * produire deux acceptations pour une seule intervention.
     */
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

    /**
     * Ouvre — ou retrouve — la recherche d'une intervention immédiate.
     *
     * La ligne de panier est RATTACHÉE quand elle existe : elle porte les réponses au questionnaire
     * et sert à expliquer le devis six mois plus tard. Elle n'est pas obligatoire — une recherche
     * peut naître d'un rendez-vous converti ou d'une relance administrative, qui n'ont pas de
     * panier.
     */
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

    /**
     * Le rendez-vous planifié devient une OFFRE, plus une assignation d'office silencieuse.
     *
     * Patron « Uber Reserve » : le mieux classé reçoit la course à l'avance, avec une fenêtre de
     * réponse longue. Il peut refuser — c'est précisément ce qu'une assignation d'office lui
     * interdisait, et ce qui produisait des missions abandonnées le jour même.
     */
    public function openScheduled(Booking $booking): ?MissionAssignment
    {
        $mission = $this->ensureMission($booking);

        if (! $mission) {
            return null;
        }

        return $this->next($mission);
    }

    // ─── Progression de la chaîne ────────────────────────────────────────────────────────────

    /**
     * L'offre suivante pour cette mission — quel que soit le mode.
     *
     * Appelée par le refus, par l'expiration, et par l'ouverture. C'est le seul endroit qui décide
     * « à qui maintenant » : le dupliquer ferait diverger l'immédiat du planifié au premier
     * changement de règle.
     */
    public function next(Mission $mission): ?MissionAssignment
    {
        $search = AsapDispatchRequest::query()
            ->where('mission_id', $mission->id)
            ->open()
            ->first();

        if ($search) {
            return $this->offerNext($search);
        }

        /*
         * UNE RÉSERVATION IMMÉDIATE SANS RECHERCHE OUVERTE EN OUVRE UNE — elle ne bascule pas sur
         * le planifié.
         *
         * Le cas se produit dès qu'une mission entre par un autre chemin que la confirmation de
         * commande : `CreateBookingFromApiAction` (l'API cliente mobile) appelle directement
         * l'escalade sans passer par `dispatchBooking()`. Sans cette garde, la course partait avec
         * les règles du RENDEZ-VOUS — zones déclarées au lieu de la position GPS, trente minutes au
         * lieu de vingt secondes, aucune vague. Le client attendait devant sa porte pendant qu'on
         * interrogeait des prestataires hors ligne à l'autre bout de la zone.
         */
        $booking = $mission->booking;

        if ($booking && ($booking->booking_mode ?? null) === 'asap') {
            $ouverte = $this->openImmediate($booking);

            return $ouverte ? $this->currentOffer($ouverte->mission_id) : null;
        }

        return $this->offerScheduled($mission);
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

        /*
         * UNE SEULE OFFRE VIVANTE À LA FOIS, hors broadcast. Sans cette garde, un refus reçu
         * pendant que le job d'expiration s'exécute produirait deux offres concurrentes pour la
         * même mission — et deux prestataires qui se déplacent.
         */
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
    public function offerScheduled(Mission $mission): ?MissionAssignment
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
            return $this->assignByDefault($mission, $booking);
        }

        $candidates = $this->candidates->scheduled($booking, $tried);

        if ($candidates->isEmpty()) {
            return $this->assignByDefault($mission, $booking);
        }

        $premier = $candidates->all()[0] ?? null;

        if ($premier === null) {
            return $this->assignByDefault($mission, $booking);
        }

        return $this->createOffer(
            $mission,
            $premier->user,
            (int) Config::get('dispatch.scheduled_offer_timeout_seconds', 1800),
            $premier->distanceM,
            null,
        );
    }

    // ─── Résolutions ─────────────────────────────────────────────────────────────────────────

    /**
     * Une offre a été acceptée : la recherche se ferme et les autres modales aussi.
     *
     * Appelée par `MissionDispatchService::accept()`, APRÈS le verrou. Fermer les modales des
     * perdants n'est pas une politesse : sans ce message, leur compte à rebours continue sur une
     * course déjà partie et ils apprennent à ne plus croire les offres.
     */
    public function onAccepted(MissionAssignment $accepted): void
    {
        $missionId = (int) $accepted->mission_id;

        /*
         * LE PRESTATAIRE DEVIENT OCCUPÉ DÈS L'ACCEPTATION, pas au démarrage de l'intervention.
         *
         * C'est le patron VTC : occupé pendant la course, disponible à la fin. Sans cela, celui qui
         * vient d'accepter reste `online` et redevient candidat à la seconde suivante — il pouvait
         * donc recevoir une deuxième course immédiate alors qu'il est déjà en route pour la
         * première, et l'une des deux clientes attendait pour rien.
         *
         * `PresenceAutoTransitioner` ne basculait qu'aux transitions `en_route`/`sur_place`, c'est-
         * à-dire plusieurs minutes trop tard. Il le remet `online` à la clôture, ce qui reste vrai.
         *
         * SOFT-FAIL : une panne du module de présence ne doit pas faire échouer une acceptation
         * déjà écrite — le prestataire serait débité d'une course qu'il croit avoir prise.
         */
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

    /**
     * L'offre n'est plus à prendre : la modale se ferme d'elle-même.
     *
     * Exposé pour que le refus et l'expiration ferment l'écran sans dépendre du sondage. Sur
     * mobile, la socket est ce qui fait disparaître la modale ; sans ce message, elle resterait
     * jusqu'à ce que le compte à rebours atteigne zéro — sur une offre déjà passée au suivant.
     */
    public function withdraw(MissionAssignment $assignment, string $reason = 'taken'): void
    {
        $this->transmitter->withdraw($assignment, $reason);
    }

    /**
     * LE CLIENT A CHOISI UNE SOCIÉTÉ : ses répartiteurs sont prévenus.
     *
     * Les candidats sont déjà restreints à ses salariés — c'est ce qui garantit que la course ne
     * part pas ailleurs. Mais sans ce message, la société apprenait la demande par le salarié qui
     * l'avait acceptée, ou pas du tout si personne ne répondait : celui qui pilote l'équipe ne
     * voyait rien passer et ne pouvait pas réagir avant l'échéance.
     *
     * Le message part aux porteurs de `missions.dispatch`, c'est-à-dire à ceux qui peuvent
     * effectivement agir. Prévenir toute la société ferait sonner des téléphones qui n'ont rien à
     * décider — et l'on n'écoute plus des notifications qu'on ne peut pas suivre d'un geste.
     *
     * L'assignation interne, elle, reste au chantier société : si la société a activé son mode
     * continu, `MissionFromRendezVousSyncService` l'a déjà tentée à la naissance de la mission, et
     * la chaîne d'offres s'arrête d'elle-même dès qu'un chef est désigné.
     *
     * SOFT-FAIL : une notification qui échoue ne doit pas empêcher la recherche de démarrer.
     */
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

    /**
     * Relance une recherche épuisée — « continuer à attendre ».
     *
     * Les exclusions ne sont PAS remises à zéro : réoffrir la course à qui vient de la refuser
     * ferait vibrer son téléphone pour rien et n'apporterait aucun candidat de plus. En revanche
     * ceux qui n'avaient jamais été joints — hors ligne il y a trois minutes, en ligne maintenant —
     * entrent naturellement, puisque la liste de candidats est recalculée.
     */
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

    /**
     * Le prestataire est-il en règle côté contrôle facial pour CETTE mission ?
     */
    protected function passeLeControleFacial(User $provider, Mission $mission): bool
    {
        $booking = $mission->booking;

        $verdict = $booking !== null
            ? app(FaceCheckGate::class)->inspectForBooking($provider, $booking)
            : app(FaceCheckGate::class)->inspectProvider($provider);

        return $verdict->allowed();
    }

    /**
     * Une offre, une ligne, trois canaux.
     *
     * L'écriture en base précède l'envoi : si la diffusion échoue, l'offre existe quand même et
     * l'inbox la montre. L'inverse laisserait un prestataire prévenu d'une offre inexistante.
     */
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

        /*
         * LE CONTRÔLE FACIAL, AU MÊME ENDROIT QUE LE KYC ET POUR LA MÊME RAISON.
         *
         * `CandidateFinder` écarte déjà les visages bloqués dans le SQL, mais toutes les offres ne
         * viennent pas de lui : `assignByDefault()` et le forçage administrateur fabriquent des
         * offres sans passer par la recherche de candidats. Cette garde-ci est la seule que TOUTE
         * offre traverse.
         *
         * `null` et non une exception : c'est un chemin automatique, l'appelant passe au suivant.
         */
        if (! $this->passeLeControleFacial($provider, $mission)) {
            return null;
        }

        $assignment = DB::transaction(function () use ($mission, $provider, $ttlSeconds) {
            $now = now();

            /*
             * `updateOrCreate` et non `create` : `mission_assignments` porte un index unique
             * (mission, prestataire). Une relance après refus, ou une assignation d'office sur
             * quelqu'un déjà sollicité, ferait échouer l'insertion — et la recherche entière
             * tomberait sur une contrainte plutôt que de proposer la course.
             */
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
     * On n'y vient qu'au rayon maximal, quand attendre vingt secondes par personne coûterait plus
     * cher au client que la concurrence ne coûte aux prestataires.
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
     * Une offre `cancelled` est une offre que NOUS avons retirée : parce qu'un autre a accepté,
     * parce que le client a converti sa demande. Le prestataire ne s'est pas prononcé. La compter
     * comme un refus l'écarterait d'une question qu'on ne lui a jamais posée — typiquement « peux-tu
     * venir jeudi » après un « peux-tu venir tout de suite » retiré au bout de cinq minutes.
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

    /**
     * La mission d'exécution existe AVANT la première offre.
     *
     * `mission_assignments` s'y rattache : sans mission, il n'y a rien à proposer. Elle naît
     * `planned`, sans chef — c'est l'acceptation qui la passe à `assigned`.
     */
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

    /**
     * Le repli du planifié : personne n'a accepté, on assigne quand même.
     *
     * C'est l'ancien comportement, devenu SECOURS. Un rendez-vous confirmé doit avoir un
     * prestataire à la date dite ; l'offre est la voie courtoise, l'assignation la garantie.
     */
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

    /**
     * La trace de « qui a été prévenu, quand, à quelle distance ».
     *
     * L'index unique (recherche, prestataire) est ce qui garantit qu'on ne prévient pas deux fois :
     * l'écriture refusée est le comportement voulu, pas une erreur à remonter.
     */
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
