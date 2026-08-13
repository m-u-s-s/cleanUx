<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\Concerns\FormatsBookingSchedule;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionVerificationCodeService;
use App\Services\Notifications\SmsService;
use App\Services\Payments\PayoutAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 12 — Lifecycle d'une mission côté prestataire mobile.
 *
 * GET    /api/provider/missions/active            → mes missions actives
 * GET    /api/provider/missions/{id}              → détail
 * POST   /api/provider/missions/{id}/start        → "je pars" (en_route)
 * POST   /api/provider/missions/{id}/arrive       → "je suis arrivé"
 * POST   /api/provider/missions/{id}/complete     → "j'ai terminé" (avec code de fin)
 *
 * Wraps autour du MissionLifecycleService existant (méthodes setEnRoute,
 * setArrived, completeMission). Garantit que le user a bien le droit d'agir
 * sur la mission (assignment ou lead).
 */
/**
 * @group Mission Lifecycle
 *
 * @authenticated
 */
class ProviderMissionLifecycleController extends Controller
{
    use FormatsBookingSchedule;

    /**
     * Colonnes de réservation nécessaires au payload plat. Chargées sur LES DEUX chaînes de
     * résolution unique : `booking()`, la seule relation depuis la fusion des deux colonnes.
     */
    private const BOOKING_COLUMNS = 'id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment,client_id,customer_user_id';

    public function __construct(
        protected MissionLifecycleService $lifecycle,
    ) {}

    /**
     * @return array<int, string>
     */
    private function bookingEagerLoads(): array
    {
        $loads = [];

        foreach (['booking'] as $relation) {
            $loads[] = $relation.':'.self::BOOKING_COLUMNS;
            $loads[] = $relation.'.serviceCatalog:id,name';
            $loads[] = $relation.'.client:id,name,phone';
            $loads[] = $relation.'.customer:id,name,phone';
        }

        return $loads;
    }

    /**
     * List the provider's active missions (assigned, en_route, arrived, started, paused).
     *
     * @response 200 {"ok": true, "count": 1, "data": [{"id": 12, "status": "assigned", "service_name": "Nettoyage domicile", "client_name": "Alice Dupont", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "latitude": 50.846, "longitude": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "booking_id": 4, "booking_reference": "CUX-A1B2C3", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null}]}
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        $missions = Mission::query()
            ->where(function ($q) use ($user) {
                $q->where('lead_provider_user_id', $user->id)
                    ->orWhereHas('assignments', function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                            ->where('assignment_status', 'accepted');
                    })
                    /*
                     * LE RENFORT D'UNE MISSION DE SOCIÉTÉ NE VOYAIT RIEN.
                     *
                     * Cette liste n'admettait que le responsable et les assignments `accepted`.
                     * Or `accepted` est l'état du parcours MARKETPLACE — un indépendant à qui l'on
                     * propose une mission et qui répond oui. Un salarié, lui, n'accepte rien : son
                     * employeur décide, et l'assignation naît `assigned`. Les deux membres d'une
                     * équipe envoyée sur un chantier ne trouvaient donc leur mission nulle part.
                     *
                     * `assigned` SEUL NE SUFFIT PAS COMME CRITÈRE : c'est aussi l'état d'une OFFRE
                     * en attente de réponse (`MissionDispatchService`). L'ouvrir sans condition
                     * laisserait un indépendant démarrer une mission qu'on lui a seulement proposée.
                     * C'est l'appartenance de la MISSION à une société prestataire qui départage :
                     * là, l'assignation est une décision, pas une proposition.
                     */
                    ->orWhere(function ($q3) use ($user) {
                        $q3->whereNotNull('provider_organization_id')
                            ->whereHas('assignments', function ($q4) use ($user) {
                                $q4->where('user_id', $user->id)
                                    ->where('assignment_status', 'assigned');
                            });
                    });
            })
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'started', 'paused'])
            ->with($this->bookingEagerLoads())
            ->orderBy('planned_start_at')
            ->get();

        return response()->json([
            'ok' => true,
            'count' => $missions->count(),
            'data' => $missions->map(fn ($m) => $this->serialize($m))->all(),
        ]);
    }

    /**
     * Get full details for a single mission including booking, client info, and checklists.
     *
     * @response 200 {"ok": true, "data": {"id": 12, "status": "assigned", "service_name": "Nettoyage domicile", "client_name": "Alice Dupont", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "latitude": 50.846, "longitude": 4.352, "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "booking_id": 4, "booking_reference": "CUX-A1B2C3", "planned_start_at": "2026-06-15T09:00:00+00:00", "actual_start_at": null, "actual_end_at": null, "estimated_duration_minutes": 120, "actual_duration_minutes": null, "client_phone": "+32471000001", "notes": "Apporter matériel", "total_price": 75.0, "provider_cost": 55.0, "checklists_count": 1, "checklist_items_pending": 5}}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $mission->load(array_merge($this->bookingEagerLoads(), [
            'assignments',
            'checklists.items',
        ]));

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($mission, detailed: true),
        ]);
    }

    /**
     * Mark the mission as en_route (provider is departing toward the client).
     *
     * @bodyParam lat numeric Current GPS latitude of the provider (-90 to 90). Example: 50.843
     * @bodyParam lng numeric Current GPS longitude of the provider (-180 to 180). Example: 4.348
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "en_route"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"message": "Mission cannot transition from current status to en_route."}
     */
    public function start(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Lifecycle exceptions propagate to ApiJsonRenderer for unified JSON error shape.
        $mission = $this->lifecycle->setEnRoute($mission, $request->user());

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /**
     * Mark the mission as arrived (provider is at the client's location).
     *
     * @bodyParam lat numeric GPS latitude at arrival (-90 to 90). Example: 50.846
     * @bodyParam lng numeric GPS longitude at arrival (-180 to 180). Example: 4.352
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "arrived"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"message": "Mission cannot transition from current status to arrived."}
     */
    /**
     * Close the mission with the code the client shows on their screen.
     *
     * Mirror of the presence scan, at the other end of the visit. The end code was sent to the
     * client by SMS and typed back by the provider; an SMS travels, a code read off the client's
     * screen requires both people in the same room. Closing captures the pre-authorised payment,
     * so the client's assent must be a deliberate gesture rather than a forwarded message.
     *
     * @bodyParam code string required The 6-digit code read from the client's QR. Example: 731204
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Code invalide."}
     */
    public function completeByQr(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:191'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'mocked' => ['nullable', 'boolean'],
        ]);

        try {
            // `validateEndCode` consomme le code PUIS clôture — encaissement compris. Un code
            // refusé ne doit donc rien déclencher, d'où la validation en amont de la clôture.
            //
            // C'est le SEUL chemin de clôture où la position est exigée, et c'est voulu : ici
            // l'application en dispose toujours, et c'est ici qu'un code photographié ou dicté
            // servirait à clôturer à distance. Les chemins web n'ont pas de position à offrir.
            $mission = $this->lifecycle->validateEndCode(
                $mission,
                $request->user(),
                $data['code'],
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
                isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
                (bool) ($data['mocked'] ?? false),
                requirePosition: (bool) config('trip_tracking.presence_require_position', true),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
            // La clôture par scan mérite la même annonce que la clôture par code saisi.
            'payout' => $this->annoncePayout($mission),
        ]);
    }

    public function arrive(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Lifecycle exceptions propagate to ApiJsonRenderer for unified JSON error shape.
        $mission = $this->lifecycle->setArrived(
            $mission,
            $request->user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null,
        );

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /**
     * Mark the mission as completed.
     *
     * If the booking has an end QR-code set up, `end_code` is required. Pass the 6-digit
     * code scanned from the client's QR at end-of-service.
     *
     * @bodyParam lat numeric GPS latitude at completion (-90 to 90). Example: 50.846
     * @bodyParam lng numeric GPS longitude at completion (-180 to 180). Example: 4.352
     * @bodyParam end_code string 6-digit end verification code from client QR (required when booking has end code). Example: 482951
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed", "duration_minutes": 118}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Le code de fin est requis pour clôturer cette mission."}
     */
    /**
     * Démarre une mission sur laquelle le prestataire est arrivé (arrived → started).
     *
     * Ce passage n'existait que sur des routes WEB à session (routes/missions.php), inaccessibles
     * à une app authentifiée par jeton : un prestataire arrivé sur place ne pouvait pas démarrer
     * sa mission depuis son téléphone. `start` ci-dessus ne fait PAS cela — il met en route
     * (setEnRoute), et l'appeler depuis `arrived` produit un 422, la transition étant invalide.
     *
     * Le code de début est envoyé au client par SMS à l'arrivée (setArrived) : c'est lui qui
     * atteste sa présence. Même contrat que `complete` et son end_code.
     *
     * @bodyParam start_code string Code à six chiffres communiqué par le client. Example: 482915
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "started"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Le code de début est requis pour démarrer cette mission."}
     */
    /**
     * RENVOYER AU CLIENT LE CODE QU'IL N'A PAS REÇU.
     *
     * Un SMS se perd : réseau du client, numéro mal saisi, message noyé, plafond d'envoi atteint.
     * Sans ce geste, l'intervention s'arrêtait là — le prestataire est devant la porte, le client
     * n'a pas ses six chiffres, et aucun des deux ne peut rien y faire. Le seul recours était
     * d'annuler la mission.
     *
     * LE CODE PRÉCÉDENT EST INVALIDÉ, et c'est délibéré : `createVerificationCode()` consomme
     * l'ancien. Deux codes valides pour la même mission feraient hésiter un client qui a reçu les
     * deux SMS — et le mauvais choix brûle un essai.
     *
     * UNE ATTENTE SÉPARE DEUX RENVOIS. Le plafond du module SMS est de cinq messages par heure et
     * par numéro : trois pressions distraites suffisaient à l'épuiser, après quoi le client ne
     * recevait plus RIEN — ni ce code-ci, ni celui de fin. C'est exactement ce qui s'est produit
     * sur la base de démonstration, et le statut `rate_limited` du registre était le seul endroit
     * où cela se lisait.
     *
     * @bodyParam type string Le code à renvoyer : `start` ou `end`. Example: start
     *
     * @response 200 {"ok": true, "type": "start", "sent_to": "+3247******99"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 409 {"ok": false, "message": "Patientez avant de renvoyer un nouveau code."}
     * @response 422 {"ok": false, "message": "Aucun numéro de téléphone au dossier du client."}
     */
    public function resendCode(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:start,end'],
        ]);

        /*
         * DEUX SOURCES, DANS CET ORDRE : le compte du client, puis le numéro saisi sur la
         * réservation. Une commande passée pour un tiers — un parent, un locataire — porte le
         * second et pas le premier ; ne lire que le compte enverrait le code à la mauvaise
         * personne, ou à personne.
         */
        $rendezVous = $mission->booking;
        $telephone = $rendezVous?->client?->phone ?: $rendezVous?->telephone_client;

        if (! $telephone) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucun numéro de téléphone au dossier du client.',
            ], 422);
        }

        $cle = 'mission_code_resend:'.$mission->id.':'.$data['type'];
        $attente = (int) config('trip_tracking.code_resend_cooldown_seconds', 60);

        if (Cache::has($cle)) {
            return response()->json([
                'ok' => false,
                'message' => 'Patientez avant de renvoyer un nouveau code.',
                'retry_after_seconds' => $attente,
            ], 409);
        }

        /*
         * LE CODE DE FIN PASSE PAR LE CYCLE DE VIE, PAS PAR LE SERVICE DE CODES.
         *
         * `issueEndCode()` émet le code ET le confie au suivi web du client, en plus du SMS. Créer
         * le code ici en direct ne laissait qu'un SMS pour le transporter — or c'est justement son
         * absence qui motive ce renvoi : le client qui n'a rien reçu la première fois ne recevrait
         * toujours rien si le plafond d'envoi est déjà épuisé.
         */
        if ($data['type'] === 'end') {
            $genere = $this->lifecycle->issueEndCode($mission);
        } else {
            $genere = app(MissionVerificationCodeService::class)->createVerificationCode($mission, 'start');

            app(SmsService::class)->send(
                $telephone,
                'Brio : votre employé est arrivé. Code de début : '.$genere['code'],
            );
        }

        Cache::put($cle, true, $attente);

        return response()->json([
            'ok' => true,
            'type' => $data['type'],
            // Le numéro est MASQUÉ : il confirme au prestataire qu'on a écrit au bon client sans
            // lui livrer le téléphone de quelqu'un chez qui il n'ira peut-être jamais.
            'sent_to' => $this->telephoneMasque((string) $telephone),
        ]);
    }

    /** Garde les quatre premiers caractères et les deux derniers : « +3247******99 ». */
    protected function telephoneMasque(string $telephone): string
    {
        $longueur = mb_strlen($telephone);

        if ($longueur <= 6) {
            return str_repeat('*', $longueur);
        }

        return mb_substr($telephone, 0, 4)
            .str_repeat('*', max(1, $longueur - 6))
            .mb_substr($telephone, -2);
    }

    public function begin(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'start_code' => ['nullable', 'string', 'size:6'],
        ]);

        $hasPendingStartCode = $mission->verificationCodes()
            ->where('code_type', 'start')
            ->where('is_consumed', false)
            ->exists();

        if ($hasPendingStartCode && empty($data['start_code'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Le code de début est requis pour démarrer cette mission.',
            ], 422);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        // Sans code en attente, la mission n'a pas été marquée arrivée par le flux normal : on
        // refuse plutôt que d'inventer un démarrage sans attestation de présence.
        if (! $hasPendingStartCode) {
            return response()->json([
                'ok' => false,
                'message' => "Aucun code de début en attente : marquez d'abord votre arrivée.",
            ], 422);
        }

        // Même raison qu'à la clôture : « Code invalide » ou « Le code a expiré » doivent être lus
        // par le prestataire, pas remplacés par une erreur générique.
        try {
            $mission = $this->lifecycle->validateStartCode(
                $mission,
                $request->user(),
                (string) $data['start_code'],
                $lat,
                $lng,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    public function complete(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'end_code' => ['nullable', 'string', 'size:6'],
        ]);

        $hasPendingEndCode = $mission->verificationCodes()
            ->where('code_type', 'end')
            ->where('is_consumed', false)
            ->exists();

        if ($hasPendingEndCode && empty($data['end_code'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Le code de fin est requis pour clôturer cette mission.',
            ], 422);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        /*
         * LA RAISON DU REFUS DOIT ARRIVER JUSQU'AU PRESTATAIRE.
         *
         * Sans ce rattrapage, toute `RuntimeException` du cycle de vie filait vers le rendu
         * générique et le téléphone affichait « An unexpected error occurred ». Or ces exceptions
         * portent exactement ce qu'il faut faire : « certaines tâches obligatoires ne sont pas
         * cochées », « Le code a expiré », « Code invalide ». Le prestataire lisait un message qui
         * ne lui disait rien pendant que le serveur, lui, savait précisément quoi répondre.
         *
         * `completeByQr` le faisait déjà ; ce chemin-ci et `begin()` avaient été oubliés.
         */
        try {
            if ($hasPendingEndCode && ! empty($data['end_code'])) {
                $mission = $this->lifecycle->validateEndCode($mission, $request->user(), $data['end_code'], $lat, $lng);
            } else {
                $mission = $this->lifecycle->completeMission($mission, $request->user(), $lat, $lng);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
            'duration_minutes' => $mission->actual_duration_minutes,
            // Ce que le prestataire doit lire À L'INSTANT où il clôture : combien, et quand. La
            // notification durable part en parallèle, mais un message qui arrive plus tard ne
            // répond pas à la question qu'il se pose en rangeant son matériel.
            'payout' => $this->annoncePayout($mission),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function annoncePayout(Mission $mission): ?array
    {
        try {
            return app(PayoutAnnouncementService::class)->pour($mission);
        } catch (\Throwable $e) {
            // Une annonce indisponible ne doit pas transformer une clôture réussie en erreur.
            return null;
        }
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function authorizeProvider(Request $request, Mission $mission): void
    {
        $userId = $request->user()->id;

        $isLead = (int) $mission->lead_provider_user_id === (int) $userId;
        $isAssigned = $mission->assignments()
            ->where('user_id', $userId)
            ->whereIn('assignment_status', ['accepted', 'en_route', 'arrived'])
            ->exists();

        /*
         * Le RENFORT d'une mission de société — même règle que la liste `active()`.
         *
         * Sans cela, un membre d'équipe pouvait voir sa mission dans la liste et se voir refuser
         * son ouverture : il n'a rien « accepté », son employeur a décidé pour lui. La condition
         * sur `provider_organization_id` est ce qui distingue une DÉCISION d'une OFFRE en attente
         * de réponse, les deux portant `assigned`.
         */
        $isSalarieAssigne = $mission->provider_organization_id !== null
            && $mission->assignments()
                ->where('user_id', $userId)
                ->where('assignment_status', 'assigned')
                ->exists();

        abort_if(
            ! $isLead && ! $isAssigned && ! $isSalarieAssigne,
            403,
            "Vous n'êtes pas assigné à cette mission."
        );
    }

    /**
     * Payload PLAT, aligné sur le type TS `Mission` (mobile/provider/src/missions/types.ts) que
     * consomment MissionDetailScreen, TrackingScreen et MissionsListScreen. Même traitement que
     * ProviderMissionAssignmentController::serializeForList() pour l'inbox : la structure
     * imbriquée { booking: {...}, client: {...} } rendait chacune de ces lectures `undefined`.
     */
    protected function serialize(Mission $mission, bool $detailed = false): array
    {
        // `missions` n'a plus qu'une clé vers `bookings`. La relation choisissait auparavant sa
        // colonne à l'exécution, ce que le chargement anticipé de Laravel ne sait pas faire : il
        // résout la relation sur une instance vierge, où l'attribut est vide, et retombait donc
        // toujours du même côté. Un prestataire sur deux voyait des tirets à la place du client.
        $booking = $mission->booking;
        $client = $booking?->client ?? $booking?->customer;

        $base = [
            'id' => $mission->id,
            'status' => $mission->status,
            'service_name' => $booking?->serviceCatalog?->name,
            'client_name' => $client?->name,
            'address' => $booking?->address,
            'city' => $booking?->city,
            'postal_code' => $booking?->postal_code,
            // Destination de la mission, c'est-à-dire l'endroit où le prestataire doit se rendre :
            // c'est ce dont TrackingScreen a besoin pour la distance, l'ETA et le géofence
            // d'arrivée à 150 m. Surtout PAS start_lat/end_lat, qui portent la position GPS du
            // PRESTATAIRE au départ et à la clôture (MissionLifecycleService) : les utiliser
            // ferait converger la distance vers zéro dès le départ.
            'latitude' => $this->toFloat($mission->destination_lat ?? $booking?->destination_lat),
            'longitude' => $this->toFloat($mission->destination_lng ?? $booking?->destination_lng),
            'scheduled_date' => $this->formatScheduledDate($booking?->scheduled_date),
            'scheduled_time' => $this->formatScheduledTime($booking?->scheduled_time),
            'booking_id' => $booking?->id,
            'booking_reference' => $booking?->booking_reference,
            'planned_start_at' => $mission->planned_start_at?->toIso8601String(),
            'actual_start_at' => $mission->actual_start_at?->toIso8601String(),
            'actual_end_at' => $mission->actual_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $mission->estimated_duration_minutes,
            'actual_duration_minutes' => $mission->actual_duration_minutes,
        ];

        if ($detailed) {
            $base['client_phone'] = $client?->phone;
            $base['notes'] = $booking?->customer_comment;
            $base['total_price'] = $this->toFloat($mission->client_price);
            $base['provider_cost'] = $this->toFloat($mission->provider_cost);
            $base['checklists_count'] = $mission->checklists->count();
            $base['checklist_items_pending'] = $mission->checklists
                ->flatMap(fn ($c) => $c->items)
                ->where('status', '!=', 'done')
                ->count();
        }

        return $base;
    }

    /** Les colonnes décimales reviennent en chaîne (cast decimal:7) : le mobile attend un nombre. */
    private function toFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
