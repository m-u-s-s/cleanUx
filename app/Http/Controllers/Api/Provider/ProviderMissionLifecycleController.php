<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\Concerns\FormatsBookingSchedule;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\Missions\HourlyMissionClock;
use App\Services\Missions\MissionDelayService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionVerificationCodeService;
use App\Services\Missions\RideLifecycleService;
use App\Services\Notifications\SmsService;
use App\Services\Payments\PayoutAnnouncementService;
use App\Support\Domain\MissionEngine;
use App\Support\Domain\MissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Phase 12 — Lifecycle d'une mission côté prestataire mobile. */
/**
 * @group Mission Lifecycle
 *
 * @authenticated
 */
class ProviderMissionLifecycleController extends Controller
{
    use FormatsBookingSchedule;

    /** Colonnes de réservation nécessaires au payload plat. */
    /** Colonnes de réservation nécessaires au payload plat. */
    private const BOOKING_COLUMNS = 'id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,trade_id,destination_lat,destination_lng,dropoff_address,dropoff_lat,dropoff_lng,route_distance_m,customer_comment,client_id,customer_user_id';

    public function __construct(
        protected MissionLifecycleService $lifecycle,
        protected HourlyMissionClock $horloge,
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
            // LE MÉTIER, PARCE QUE LE CATALOGUE NE RÉPOND PLUS. Le moteur de commande écrit
            // `trade_id` et laisse `service_catalog_id` vide ; seules les réservations d'archive
            // portent encore un service au catalogue. Sans cette relation, `service_name` valait
            // `null` pour TOUTE mission née du moteur — et l'écran terrain affichait un titre vide.
            $loads[] = $relation.'.trade:id,name';
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
                    // LE RENFORT D'UNE MISSION DE SOCIÉTÉ NE VOYAIT RIEN.
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
     * @bodyParam code string required The 6-digit code read from the client's QR. Example: 731204
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Code invalide."}
     */
    public function completeByQr(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        if ($refus = $this->refuseSiCourse($mission)) {
            return $refus;
        }

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
     * @bodyParam start_code string Code à six chiffres communiqué par le client. Example: 482915
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "started"}
     * @response 403 {"message": "Vous n'êtes pas assigné à cette mission."}
     * @response 422 {"ok": false, "message": "Le code de début est requis pour démarrer cette mission."}
     */
    /**
     * RENVOYER AU CLIENT LE CODE QU'IL N'A PAS REÇU.
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

        // DEUX SOURCES, DANS CET ORDRE : le compte du client, puis le numéro saisi sur la réservation.
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

        // LE CODE DE FIN PASSE PAR LE CYCLE DE VIE, PAS PAR LE SERVICE DE CODES.
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

    /** LE CLIENT EST À BORD — la course démarre. */
    public function startRide(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $mission = app(RideLifecycleService::class)->demarrerLaCourse(
                $mission,
                $request->user(),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /** ARRIVÉ À DESTINATION — la course se termine, et le paiement est capturé. */
    public function completeRide(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $mission = app(RideLifecycleService::class)->terminerLaCourse(
                $mission,
                $request->user(),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
            'duration_minutes' => $mission->actual_duration_minutes,
            'payout' => $this->annoncePayout($mission),
        ]);
    }

    /** LES DEUX PARCOURS NE SE CROISENT PAS — et le refus DIT pourquoi. */
    private function refuseSiCourse(Mission $mission): ?JsonResponse
    {
        if (! app(RideLifecycleService::class)->estUneCourse($mission)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Cette mission est une course : utilisez « client à bord » puis « terminer la course ». Elle n’a pas de code.',
        ], 409);
    }

    public function begin(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        if ($refus = $this->refuseSiCourse($mission)) {
            return $refus;
        }

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

        if ($refus = $this->refuseSiCourse($mission)) {
            return $refus;
        }

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'end_code' => ['nullable', 'string', 'size:6'],
        ]);

        // L'ACCORD DU CLIENT EST EXIGÉ PARCE QUE LA MISSION L'EXIGE — pas parce qu'un code traîne.
        $exigeUnCodeDeFin = (bool) $mission->requires_end_code;

        $codeDeFinEnAttente = $mission->verificationCodes()
            ->where('code_type', 'end')
            ->where('is_consumed', false)
            ->exists();

        if ($exigeUnCodeDeFin && empty($data['end_code'])) {
            return response()->json([
                'ok' => false,
                'message' => $codeDeFinEnAttente
                    ? 'Le code de fin est requis pour clôturer cette mission.'
                    : 'Demandez au client son code de fin — il l’obtient depuis son espace, ou par SMS via « Renvoyer le SMS ».',
            ], 422);
        }

        $lat = isset($data['lat']) ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) ? (float) $data['lng'] : null;

        // LA RAISON DU REFUS DOIT ARRIVER JUSQU'AU PRESTATAIRE.
        try {
            // SIX CHIFFRES FOURNIS SE VALIDENT — TOUJOURS.
            if (! empty($data['end_code'])) {
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

        // Le RENFORT d'une mission de société — même règle que la liste `active()`.
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

    /** Payload PLAT, aligné sur le type TS `Mission` (mobile/provider/src/missions/types.ts) que consomment MissionDetailScreen, TrackingScreen et MissionsListScreen. */
    /** Quand le conducteur pourra déclarer que le client ne s'est pas présenté. */
    protected function absenceDeclarableA(Mission $mission): ?string
    {
        if (! $mission->booking?->estUneCourse() || $mission->status !== MissionStatus::ARRIVED) {
            return null;
        }

        $arrivee = $mission->assignments
            ->whereNotNull('arrived_at')
            ->sortByDesc('arrived_at')
            ->first()?->arrived_at;

        if (! $arrivee) {
            return null;
        }

        return Carbon::parse($arrivee)
            ->addMinutes((int) config('cancellation.no_show.ride_grace_minutes', 5))
            ->toIso8601String();
    }

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
            // LE CATALOGUE D'ABORD, LE MÉTIER ENSUITE — le même ordre que `OfferPayloadBuilder`.
            'service_name' => $booking?->serviceCatalog->name ?? $booking?->trade?->name,
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
            // CE QUI DIT À L'ÉCRAN QUEL PARCOURS DÉROULER.
            'is_ride' => (bool) $booking?->estUneCourse(),
            // LE MOTEUR, TRANCHÉ PAR LE SERVEUR — et c'est ce qui décide de la page à dérouler.
            'engine' => MissionEngine::pourMission($mission),
            // Le point de dépose, pour la carte et pour l'annonce « vous allez à… ».
            'dropoff' => $booking?->estUneCourse() ? [
                'address' => $booking->dropoff_address,
                'latitude' => $this->toFloat($booking->dropoff_lat),
                'longitude' => $this->toFloat($booking->dropoff_lng),
                'distance_m' => $booking->route_distance_m,
            ] : null,
            // L'INSTANT À PARTIR DUQUEL L'ABSENCE PEUT ÊTRE DÉCLARÉE.
            'no_show_available_at' => $this->absenceDeclarableA($mission),
            // LE COMPTEUR D'UNE MISSION VENDUE AU TEMPS.
            'clock' => $this->horloge->etat($mission),
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

    /** LE RETARD, VU DU PRESTATAIRE. Il n'a pas besoin qu'on le lui apprenne — il a une montre. */
    public function retard(Request $request, Mission $mission, MissionDelayService $retards): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $booking = $mission->booking;

        if ($booking === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $retards->etat($booking)]);
    }

    /** ANNONCER SON RETARD — la seule action qui évite l'annulation. */
    public function annoncerLeRetard(Request $request, Mission $mission, MissionDelayService $retards): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $booking = $mission->booking;

        if ($booking === null) {
            return response()->json(['message' => 'Reservation introuvable.'], 422);
        }

        $donnees = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'arrival_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:180'],
        ]);

        $arrivee = null;

        if (($donnees['arrival_at'] ?? null) !== null) {
            $arrivee = Carbon::parse($donnees['arrival_at']);
        } elseif (($donnees['minutes'] ?? null) !== null) {
            $arrivee = Carbon::now()->addMinutes((int) $donnees['minutes']);
        }

        return response()->json([
            'ok' => true,
            'data' => $retards->annoncerParLePrestataire($booking, $arrivee, $donnees['reason'] ?? null),
        ]);
    }
}
