<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
 * PrendreRendezVous existant. L'API mobile fait une création "simplifiée"
 * suffisante pour les cas d'usage mobile (booking minimal viable).
 */
class ClientBookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $params = $request->validate([
            'status'      => ['nullable', 'string', 'max:32'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date', 'after_or_equal:from'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ]);

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
            'ok'         => true,
            'data'       => collect($paginator->items())->map(fn ($b) => $this->serialize($b))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $booking->load([
            'serviceCatalog:id,name,trade_id',
            'organizationSite:id,name,address,city',
            'serviceZone:id,name',
            'assignedProvider:id,name,phone',
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->serialize($booking, detailed: true),
        ]);
    }

    /**
     * Création simplifiée pour mobile. Pour des cas complexes (organization sites,
     * recurring series, etc.), le client doit utiliser le flow web complet.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'service_catalog_id'   => ['required', 'integer', 'exists:service_catalogs,id'],
            'address'              => ['required', 'string', 'max:255'],
            'city'                 => ['required', 'string', 'max:120'],
            'postal_code'          => ['required', 'string', 'max:20'],
            'country'              => ['nullable', 'string', 'size:2'],
            'scheduled_date'       => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time'       => ['required', 'date_format:H:i'],
            'booking_mode'         => ['nullable', 'in:scheduled,asap'],
            'surface_m2'           => ['nullable', 'numeric', 'min:0'],
            'customer_comment'     => ['nullable', 'string', 'max:2000'],
            'priority'             => ['nullable', 'in:normal,urgent,low'],
            'contact_name'         => ['nullable', 'string', 'max:120'],
            'contact_phone'        => ['nullable', 'string', 'max:30'],
            'destination_lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lng'      => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $now = now();
        $isAsap = ($data['booking_mode'] ?? 'scheduled') === 'asap';

        $booking = Booking::create([
            'booking_reference' => $this->generateReference(),
            'customer_user_id'  => $user->id,
            'client_id'         => $user->id,
            'customer_organization_id' => $user->organization_account_id ?? $user->current_organization_id ?? null,
            'service_catalog_id' => $data['service_catalog_id'],
            'address'           => $data['address'],
            'city'              => $data['city'],
            'postal_code'       => $data['postal_code'],
            'country'           => $data['country'] ?? 'BE',
            'scheduled_date'    => $data['scheduled_date'],
            'scheduled_time'    => $data['scheduled_time'] . ':00',
            'booking_mode'      => $isAsap ? 'asap' : 'scheduled',
            'status'            => $isAsap ? 'confirme' : 'en_attente',
            'priority'          => $data['priority'] ?? ($isAsap ? 'urgent' : 'normal'),
            'surface_m2'        => $data['surface_m2'] ?? null,
            'customer_comment'  => $data['customer_comment'] ?? null,
            'contact_name'      => $data['contact_name'] ?? $user->name,
            'contact_phone'     => $data['contact_phone'] ?? ($user->phone ?? null),
            'destination_lat'   => $data['destination_lat'] ?? null,
            'destination_lng'   => $data['destination_lng'] ?? null,
            'currency'          => $user->preferred_currency ?? 'EUR',
            'created_by'        => $user->id,
            'asap_requested_at' => $isAsap ? $now : null,
            'asap_deadline_at'  => $isAsap ? $now->copy()->addHours(2) : null,
        ]);

        // Créer la mission si ASAP (pour dispatch immédiat via Phase 11)
        if ($isAsap && class_exists(Mission::class)) {
            $mission = Mission::create([
                'booking_id'       => $booking->id,
                'status'           => 'planned',
                'planned_start_at' => $now->copy()->addMinutes(30),
            ]);

            // Trigger dispatch si Phase 11 installée
            $dispatchClass = '\App\Services\Dispatch\MissionDispatchService';
            if (class_exists($dispatchClass)) {
                try {
                    app($dispatchClass)->dispatchToNextProvider($mission);
                } catch (\Throwable $e) {
                    \Log::warning('Auto-dispatch failed', [
                        'mission_id' => $mission->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'ok'   => true,
            'data' => $this->serialize($booking->fresh()),
        ], 201);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Bookings finals : pas annulables
        $finalStatuses = ['termine', 'completed', 'annule', 'cancelled', 'refuse', 'sur_place', 'on_site'];
        if (in_array((string) $booking->status, $finalStatuses, true)) {
            return response()->json([
                'ok'    => false,
                'error' => "Cette réservation ne peut plus être annulée (statut: {$booking->status}).",
            ], 409);
        }

        $booking->update([
            'status'              => 'annule',
            'cancelled_at'        => now(),
            'cancelled_by'        => $request->user()->id,
            'cancellation_reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->serialize($booking->fresh()),
        ]);
    }

    /**
     * ETA temps réel.
     *
     * Retourne :
     *   - position courante du prestataire (si tracking actif)
     *   - distance restante (calculée si on a destination_lat/lng)
     *   - temps estimé (basé sur vitesse moyenne 30 km/h en ville)
     *
     * NB : la version pleine d'ETA via Google Distance Matrix viendra en
     * Phase 13. Pour l'instant on retourne une estimation Haversine.
     */
    public function eta(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeAccess($request, $booking);

        $mission = $booking->missions()->latest()->first();
        if (! $mission) {
            return response()->json([
                'ok'    => true,
                'eta'   => null,
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
                'ok'         => true,
                'state'      => 'no_tracking',
                'mission_id' => $mission->id,
                'status'     => $mission->status,
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
            'ok'                  => true,
            'state'               => 'tracking',
            'mission_id'          => $mission->id,
            'mission_status'      => $mission->status,
            'provider_position'   => [
                'lat'              => $providerLat,
                'lng'              => $providerLng,
                'last_update_at'   => $session->updated_at?->toIso8601String(),
            ],
            'destination'         => $destLat && $destLng ? [
                'lat' => $destLat,
                'lng' => $destLng,
            ] : null,
            'distance_km'         => $distanceKm ? round($distanceKm, 2) : null,
            'eta_minutes'         => $etaMinutes,
            'is_client_visible'   => (bool) $session->is_client_visible,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

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

        abort_if(! $isOwner && ! $isOrgMember && ! $isAdmin, 403, "Accès refusé.");
    }

    protected function serialize(Booking $b, bool $detailed = false): array
    {
        $base = [
            'id'                 => $b->id,
            'reference'          => $b->booking_reference,
            'status'             => $b->status,
            'mode'               => $b->booking_mode ?? 'scheduled',
            'priority'           => $b->priority ?? 'normal',
            'scheduled_date'     => $b->scheduled_date instanceof \Carbon\Carbon
                                    ? $b->scheduled_date->toDateString()
                                    : (string) $b->scheduled_date,
            'scheduled_time'     => $b->scheduled_time
                                    ? \Carbon\Carbon::parse($b->scheduled_time)->format('H:i')
                                    : null,
            'service_name'       => $b->serviceCatalog?->name,
            'address'            => $b->address,
            'city'               => $b->city,
            'postal_code'        => $b->postal_code,
            'estimated_price'    => $b->estimated_price ? (float) $b->estimated_price : null,
            'currency'           => $b->currency ?? 'EUR',
            'created_at'         => $b->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $base = array_merge($base, [
                'customer_comment' => $b->customer_comment ?? null,
                'surface_m2'       => $b->surface_m2,
                'site_name'        => $b->organizationSite?->name,
                'destination_lat'  => $b->destination_lat,
                'destination_lng'  => $b->destination_lng,
                'cancelled_at'     => $b->cancelled_at?->toIso8601String(),
                'cancellation_reason' => $b->cancellation_reason,
                'asap_requested_at' => $b->asap_requested_at?->toIso8601String(),
                'asap_deadline_at'  => $b->asap_deadline_at?->toIso8601String(),
                'assigned_provider' => $b->assignedProvider ? [
                    'id'    => $b->assignedProvider->id,
                    'name'  => $b->assignedProvider->name,
                    'phone' => $b->assignedProvider->phone ?? null,
                ] : null,
            ]);
        }

        return $base;
    }

    protected function generateReference(): string
    {
        do {
            $ref = 'CUX-' . strtoupper(bin2hex(random_bytes(3)));
        } while (Booking::where('booking_reference', $ref)->exists());

        return $ref;
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
