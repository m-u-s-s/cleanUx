<?php

namespace App\Http\Controllers\Api\Client;

use App\Actions\Booking\CreateBookingFromApiAction;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Client\IndexBookingRequest;
use App\Http\Requests\Api\Client\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\Booking\ProviderSelectionResolver;
use App\Services\Booking\ZoneCoverageService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionVerificationCodeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Phase 12 — Bookings côté client mobile.
 *
 * GET    /api/client/bookings            → liste des bookings du client
 * POST   /api/client/bookings            → création (scheduled / asap)
 * GET    /api/client/bookings/{id}       → détail
 * POST   /api/client/bookings/{id}/cancel → annuler
 * GET    /api/client/bookings/{id}/eta   → ETA prestataire (Phase 13 will enrich)
 *
 * Sécurité : chaque endpoint vérifie que le booking appartient au user
 * (customer_user_id ou client_id ou même organisation).
 *
 * Pour création : utilise le service CreateBookingAction existant. Comme la
 * signature de ce service est complexe (PostalCode, ServiceZone, ServiceCatalog,
 * etc.), je délègue à un wrapper plus simple côté API qui résout les entités
 * depuis les IDs/codes du client.
 *
 * NB : la création complète full-featured passe par le composant Livewire
 * parcours de commande. L'API mobile fait une création "simplifiée"
 * suffisante pour les cas d'usage mobile (booking minimal viable).
 */
/**
 * @group Client Bookings
 *
 * @authenticated
 */
class ClientBookingController extends Controller
{
    public function __construct(
        protected MissionLifecycleService $lifecycle,
        protected MissionVerificationCodeService $verificationCodes,
    ) {}

    /**
     * List the authenticated client's bookings.
     *
     * @queryParam status string Filter by booking status. Example: confirme
     * @queryParam from date Filter bookings on or after this date (YYYY-MM-DD). Example: 2026-06-01
     * @queryParam to date Filter bookings on or before this date (YYYY-MM-DD). Example: 2026-06-30
     * @queryParam per_page integer Number of results per page (1-100, default 20). Example: 20
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 {"ok": true, "data": [{"id": 1, "reference": "CUX-A1B2C3", "status": "confirme", "mode": "scheduled", "priority": "normal", "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "estimated_price": 75.0, "currency": "EUR", "created_at": "2026-06-01T10:00:00+00:00"}], "pagination": {"current_page": 1, "last_page": 3, "per_page": 20, "total": 42}}
     */
    public function index(IndexBookingRequest $request): JsonResponse
    {
        $user = $request->user();

        $params = $request->validated();

        $query = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                    ->orWhere('client_id', $user->id);

                $orgId = $user->organization_account_id ?? $user->current_organization_id ?? null;
                if ($orgId) {
                    $q->orWhere('customer_organization_id', $orgId);
                }
            })
            ->with([
                'serviceCatalog:id,name',
                'organizationSite:id,name',
                'organizationContract:id,contract_reference',
            ])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_time');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (! empty($params['from'])) {
            $query->whereDate('scheduled_date', '>=', $params['from']);
        }
        if (! empty($params['to'])) {
            $query->whereDate('scheduled_date', '<=', $params['to']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => collect($paginator->items())->map(fn ($b) => $this->serialize($b))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get detailed information for a single booking.
     *
     * @response 200 {"ok": true, "data": {"id": 1, "reference": "CUX-A1B2C3", "status": "confirme", "mode": "scheduled", "priority": "normal", "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "estimated_price": 75.0, "currency": "EUR", "created_at": "2026-06-01T10:00:00+00:00", "customer_comment": "Merci d'apporter le matériel", "surface_m2": 80, "site_name": null, "destination_lat": 50.846, "destination_lng": 4.352, "cancelled_at": null, "cancellation_reason": null, "asap_requested_at": null, "asap_deadline_at": null, "assigned_provider": {"id": 7, "name": "Jean Martin", "phone": "+32471000007"}}}
     * @response 403 {"message": "Accès refusé."}
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $booking->load([
            'serviceCatalog:id,name,trade_id',
            'organizationSite:id,name,address,city',
            'serviceZone:id,name',
            'assignedProvider:id,name,phone',
            'organizationContract:id,contract_reference',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($booking, detailed: true),
        ]);
    }

    /**
     * Create a new booking (simplified mobile flow).
     *
     * For complex cases (organization sites, recurring series, etc.) use the full web flow.
     *
     * @bodyParam service_catalog_id integer required ID of the service from the catalog. Example: 3
     * @bodyParam address string required Street address of the intervention. Example: Rue de la Loi 1
     * @bodyParam city string required City of the intervention. Example: Bruxelles
     * @bodyParam postal_code string required Postal code of the intervention. Example: 1000
     * @bodyParam country string ISO 3166-1 alpha-2 country code (default BE). Example: BE
     * @bodyParam scheduled_date date required Date of the booking (today or later). Example: 2026-06-20
     * @bodyParam scheduled_time string required Time of the booking in HH:MM format. Example: 09:00
     * @bodyParam booking_mode string Mode: scheduled or asap (default scheduled). Example: scheduled
     * @bodyParam surface_m2 number Surface area in m² (optional). Example: 80
     * @bodyParam customer_comment string Special instructions for the provider (max 2000 chars). Example: Merci d'apporter le matériel
     * @bodyParam priority string Priority level: normal, urgent, low (default normal). Example: normal
     * @bodyParam contact_name string Contact name on site (defaults to authenticated user name). Example: Alice Dupont
     * @bodyParam contact_phone string Contact phone on site. Example: +32471000001
     * @bodyParam destination_lat number GPS latitude of destination. Example: 50.846
     * @bodyParam destination_lng number GPS longitude of destination. Example: 4.352
     *
     * @response 201 {"ok": true, "data": {"id": 55, "reference": "CUX-X1Y2Z3", "status": "en_attente", "mode": "scheduled", "priority": "normal", "scheduled_date": "2026-06-20", "scheduled_time": "09:00", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "estimated_price": null, "currency": "EUR", "created_at": "2026-06-01T10:00:00+00:00"}}
     * @response 422 {"message": "The service catalog id field is required.", "errors": {"service_catalog_id": ["The service catalog id field is required."]}}
     */
    public function store(StoreBookingRequest $request, CreateBookingFromApiAction $action): JsonResponse
    {
        // SP2/SP3 — gating premium + normalisation de la sélection prestataire AVANT
        // la création. Le resolver lève AuthorizationException (→ 403 via le handler
        // API) si un client non-premium tente d'imposer un nouveau prestataire non
        // favori OU une société. C'est la frontière de sécurité côté API.
        //
        // SP3 Task 6 : pour valider l'éligibilité de la société (zone+métier) sans
        // créer le booking, on construit un Booking TRANSITOIRE (non sauvegardé) porteur
        // du service_catalog_id et de la zone résolue depuis le code postal. Si la zone
        // n'est pas résolvable (booking minimal mobile), $context reste null : seul le
        // gate premium s'applique alors (l'éligibilité sera revérifiée au dispatch).
        $context = new Booking([
            'service_catalog_id' => $request->input('service_catalog_id'),
            'service_zone_id' => $this->resolveServiceZoneId($request),
        ]);

        $selection = app(ProviderSelectionResolver::class)->resolve(
            $request->user(),
            [
                'provider_type_preference' => $request->input('provider_type_preference', 'any'),
                'preferred_provider_user_id' => $request->input('preferred_provider_user_id'),
                'assigned_provider_organization_id' => $request->input('assigned_provider_organization_id'),
            ],
            $context->service_zone_id ? $context : null,
        );

        $data = array_merge($request->validated(), [
            'provider_type_preference' => $selection['provider_type_preference'],
            'preferred_provider_user_id' => $selection['preferred_provider_user_id'],
            'assigned_provider_organization_id' => $selection['assigned_provider_organization_id'],
        ]);

        $booking = $action->execute($request->user(), $data);

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($booking),
        ], 201);
    }

    /**
     * Cancel a booking.
     *
     * Cannot cancel bookings that are already cancelled or in a final state (termine, sur_place).
     *
     * @bodyParam reason string Optional reason for cancellation (max 500 chars). Example: Changement de planning
     *
     * @response 200 {"ok": true, "data": {"id": 1, "reference": "CUX-A1B2C3", "status": "annule", "mode": "scheduled", "priority": "normal", "scheduled_date": "2026-06-15", "scheduled_time": "09:00", "service_name": "Nettoyage domicile", "address": "Rue de la Loi 1", "city": "Bruxelles", "postal_code": "1000", "estimated_price": 75.0, "currency": "EUR", "created_at": "2026-06-01T10:00:00+00:00"}}
     * @response 403 {"message": "Accès refusé."}
     * @response 422 {"message": "This booking is already cancelled."}
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Bookings already cancelled: specific exception
        $alreadyCancelledStatuses = ['annule', 'cancelled', 'refuse'];
        if (in_array((string) $booking->status, $alreadyCancelledStatuses, true)) {
            throw BookingException::alreadyCancelled();
        }

        // Bookings in a final non-cancellable state
        $nonCancellableStatuses = ['termine', 'completed', 'sur_place', 'on_site'];
        if (in_array((string) $booking->status, $nonCancellableStatuses, true)) {
            throw BookingException::notCancellable();
        }

        $booking->update([
            'status' => 'annule',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->serialize($booking->fresh()),
        ]);
    }

    /**
     * Get real-time ETA for the provider en route to a booking.
     *
     * Returns the provider's current GPS position, estimated distance, and ETA
     * calculated with Haversine at 30 km/h average. Full routing via Google
     * Distance Matrix will arrive in Phase 13.
     *
     * @response 200 {"ok": true, "state": "tracking", "mission_id": 12, "mission_status": "en_route", "provider_position": {"lat": 50.843, "lng": 4.348, "last_update_at": "2026-06-15T08:45:00+00:00"}, "destination": {"lat": 50.846, "lng": 4.352}, "distance_km": 0.52, "eta_minutes": 1, "is_client_visible": true}
     * @response 200 scenario="No mission yet" {"ok": true, "eta": null, "state": "no_mission"}
     * @response 200 scenario="No tracking session" {"ok": true, "state": "no_tracking", "mission_id": 12, "status": "assigned"}
     * @response 403 {"message": "Accès refusé."}
     */
    public function eta(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $mission = $booking->missions()->latest()->first();
        if (! $mission) {
            return response()->json([
                'ok' => true,
                'eta' => null,
                'state' => 'no_mission',
            ]);
        }

        // Cherche une session de tracking active
        $session = $mission->trackingSessions()
            ->where('is_active', true)
            ->latest('started_at')
            ->first();

        if (! $session) {
            return response()->json([
                'ok' => true,
                'state' => 'no_tracking',
                'mission_id' => $mission->id,
                'status' => $mission->status,
            ]);
        }

        $providerLat = (float) $session->last_lat;
        $providerLng = (float) $session->last_lng;

        $destLat = $booking->destination_lat ? (float) $booking->destination_lat : null;
        $destLng = $booking->destination_lng ? (float) $booking->destination_lng : null;

        $distanceKm = null;
        $etaMinutes = null;
        if ($destLat && $destLng && $providerLat && $providerLng) {
            $distanceKm = $this->haversine($providerLat, $providerLng, $destLat, $destLng);
            // Estimation simpliste : 30 km/h moyenne en ville
            $etaMinutes = (int) round(($distanceKm / 30) * 60);
        }

        return response()->json([
            'ok' => true,
            'state' => 'tracking',
            'mission_id' => $mission->id,
            'mission_status' => $mission->status,
            'provider_position' => [
                'lat' => $providerLat,
                'lng' => $providerLng,
                'last_update_at' => $session->updated_at?->toIso8601String(),
            ],
            'destination' => $destLat && $destLng ? [
                'lat' => $destLat,
                'lng' => $destLng,
            ] : null,
            'distance_km' => $distanceKm ? round($distanceKm, 2) : null,
            'eta_minutes' => $etaMinutes,
            'is_client_visible' => (bool) $session->is_client_visible,
        ]);
    }

    /**
     * Confirm mission START by scanning the on-site verification code (E1).
     *
     * The client scans the 6-digit start code (shown as a QR on site). We verify the client
     * owns the booking, validate the code, then transition the mission to started. The
     * transition is attributed to the assigned provider so the existing lifecycle guards and
     * notifications run unchanged.
     *
     * @bodyParam qr_code string required The scanned verification code. Example: 482951
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "started"}
     * @response 403 {"message": "Accès refusé."}
     * @response 422 {"ok": false, "message": "Code invalide."}
     */
    public function qrStart(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);
        $data = $request->validate(['qr_code' => ['required', 'string', 'max:191']]);

        [$mission, $provider] = $this->resolveMissionAndProvider($booking);

        try {
            $this->verificationCodes->consumeValidCode($mission, 'start', $data['qr_code'], $request->user());
            $mission = $this->lifecycle->validateStartCodeFromQr($mission, $provider);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    /**
     * Confirm mission END by scanning the on-site verification code (E1).
     *
     * Validates the end code then completes the mission, which captures the pre-authorized
     * PaymentIntent. Attributed to the assigned provider so capture + payout wiring run as
     * in the provider-driven flow.
     *
     * @bodyParam qr_code string required The scanned end verification code. Example: 731204
     *
     * @response 200 {"ok": true, "mission_id": 12, "status": "completed"}
     * @response 403 {"message": "Accès refusé."}
     * @response 422 {"ok": false, "message": "Le code a expiré."}
     */
    public function qrEnd(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);
        $data = $request->validate(['qr_code' => ['required', 'string', 'max:191']]);

        [$mission, $provider] = $this->resolveMissionAndProvider($booking);

        try {
            // validateEndCode consumes the 'end' code and runs completeMission (which captures
            // the pre-authorized PaymentIntent). The code is the security factor; the client
            // owning the booking is verified above.
            $mission = $this->lifecycle->validateEndCode($mission, $provider, $data['qr_code']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mission_id' => $mission->id,
            'status' => $mission->status,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    /**
     * Resolve the latest mission of a booking and its assigned provider, or abort 422.
     *
     * @return array{0: Mission, 1: User}
     */
    protected function resolveMissionAndProvider(Booking $booking): array
    {
        $mission = $booking->missions()->latest()->first();
        abort_if(! $mission, 422, 'Aucune mission associée à cette réservation.');

        $providerId = $mission->lead_provider_user_id
            ?? $mission->assignments()
                ->whereIn('assignment_status', ['accepted', 'en_route', 'arrived'])
                ->value('user_id');
        $provider = $providerId ? User::find($providerId) : null;
        abort_if(! $provider, 422, 'Aucun prestataire assigné à cette mission.');

        return [$mission, $provider];
    }

    /**
     * Résout la service_zone_id depuis le code postal de la requête (best-effort).
     *
     * Utilisé UNIQUEMENT pour fournir un contexte d'éligibilité au
     * ProviderSelectionResolver lorsqu'une société est choisie. Si la zone n'est pas
     * résolvable, on retourne null : le resolver applique alors le seul gate premium,
     * sans validation d'éligibilité (revérifiée au dispatch).
     */
    protected function resolveServiceZoneId(Request $request): ?int
    {
        if (! $request->filled('assigned_provider_organization_id')) {
            return null; // évite un lookup zone inutile quand aucune société n'est choisie.
        }

        try {
            $coverage = app(ZoneCoverageService::class);
            $postal = $coverage->resolvePostalCode(
                $request->input('postal_code'),
                $request->input('city'),
            );

            return $coverage->resolveServiceZone($postal)?->id;
        } catch (\Throwable $e) {
            return null; // soft-fail : on retombe sur le seul gate premium.
        }
    }

    protected function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();
        $orgId = $user->organization_account_id ?? $user->current_organization_id ?? null;

        $isOwner = (int) ($booking->customer_user_id ?? 0) === (int) $user->id
                || (int) ($booking->client_id ?? 0) === (int) $user->id;

        $isOrgMember = $orgId
                    && $booking->customer_organization_id
                    && (int) $booking->customer_organization_id === (int) $orgId;

        $isAdmin = method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin();

        abort_if(! $isOwner && ! $isOrgMember && ! $isAdmin, 403, 'Accès refusé.');
    }

    /**
     * Traduit le statut brut en un état stable, sur lequel une application peut s'appuyer.
     *
     * Réutilise les aides du modèle plutôt que de redéclarer une liste de chaînes : elles
     * connaissent déjà les deux vocabulaires, et une valeur ajoutée au domaine sera reflétée ici
     * sans modification.
     */
    protected function normalisedState(Booking $b): string
    {
        return match (true) {
            $b->isCancelled() => 'cancelled',
            $b->isInProgress() => 'in_progress',
            $b->isConfirmed() => 'confirmed',
            $b->isPending() => 'pending',
            $b->isCompleted() => 'completed',
            default => 'unknown',
        };
    }

    protected function serialize(Booking $b, bool $detailed = false): array
    {
        $base = [
            'id' => $b->id,
            'reference' => $b->booking_reference,
            'status' => $b->status,
            // État normalisé, dérivé des aides du modèle. `status` porte la valeur BRUTE, et le
            // domaine emploie un vocabulaire français — en_attente, confirme, en_route,
            // sur_place, termine, annule — avec des variantes anglaises tolérées pour
            // rétrocompatibilité. Les données réelles mélangent d'ailleurs les deux.
            //
            // Les applications mobiles filtraient sur des chaînes anglaises devinées : une
            // réservation `en_attente` n'était jamais comptée comme active, et `en_route` ou
            // `sur_place` n'étaient jamais reconnues comme en cours — si bien que la carte de
            // suivi ne s'affichait jamais. Le vocabulaire se traduit ici, une seule fois, plutôt
            // que d'être redeviné dans chaque client.
            'state' => $this->normalisedState($b),
            'mode' => $b->booking_mode ?? 'scheduled',
            'priority' => $b->priority ?? 'normal',
            'scheduled_date' => $b->scheduled_date instanceof Carbon
                                    ? $b->scheduled_date->toDateString()
                                    : (string) $b->scheduled_date,
            'scheduled_time' => $b->scheduled_time
                                    ? Carbon::parse($b->scheduled_time)->format('H:i')
                                    : null,
            'service_name' => $b->serviceCatalog?->name,
            'address' => $b->address,
            'city' => $b->city,
            'postal_code' => $b->postal_code,
            'estimated_price' => $b->estimated_price ? (float) $b->estimated_price : null,
            'currency' => $b->currency ?? 'EUR',
            'contract_covered' => (bool) $b->organization_contract_id,
            'contract_label' => $b->organization_contract_id
                                    ? optional($b->organizationContract)->contract_reference
                                    : null,
            'created_at' => $b->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base = array_merge($base, [
                'customer_comment' => $b->customer_comment ?? null,
                'surface_m2' => $b->surface_m2,
                'site_name' => $b->organizationSite?->name,
                'destination_lat' => $b->destination_lat,
                'destination_lng' => $b->destination_lng,
                'cancelled_at' => $b->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $b->cancellation_reason,
                'asap_requested_at' => $b->asap_requested_at?->toIso8601String(),
                'asap_deadline_at' => $b->asap_deadline_at?->toIso8601String(),
                'assigned_provider' => $b->assignedProvider ? [
                    'id' => $b->assignedProvider->id,
                    'name' => $b->assignedProvider->name,
                    'phone' => $b->assignedProvider->phone ?? null,
                ] : null,
            ]);
        }

        return $base;
    }

    /**
     * Distance Haversine en km (formule géo simple, rapide).
     * Pour une ETA plus précise (avec routing routier), Phase 13 utilisera
     * Google Distance Matrix.
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $latFrom = deg2rad($lat1);
        $latTo = deg2rad($lat2);
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
           + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
