<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
class ProviderMissionLifecycleController extends Controller
{
    public function __construct(
        protected MissionLifecycleService $lifecycle,
    ) {}

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        $missions = Mission::query()
            ->where(function ($q) use ($user) {
                $q->where('lead_provider_user_id', $user->id)
                  ->orWhereHas('assignments', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id)
                         ->where('assignment_status', 'accepted');
                  });
            })
            ->whereIn('status', ['assigned', 'en_route', 'arrived', 'started', 'paused'])
            ->with([
                'booking:id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment',
                'booking.serviceCatalog:id,name',
            ])
            ->orderBy('planned_start_at')
            ->get();

        return response()->json([
            'ok'    => true,
            'count' => $missions->count(),
            'data'  => $missions->map(fn ($m) => $this->serialize($m))->all(),
        ]);
    }

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $mission->load([
            'booking:id,booking_reference,address,city,postal_code,scheduled_date,scheduled_time,service_catalog_id,destination_lat,destination_lng,customer_comment,client_id,customer_user_id',
            'booking.serviceCatalog:id,name',
            'booking.client:id,name,phone',
            'booking.customer:id,name,phone',
            'assignments',
            'checklists.items',
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->serialize($mission, detailed: true),
        ]);
    }

    public function start(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $mission = $this->lifecycle->setEnRoute($mission, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok'         => true,
            'mission_id' => $mission->id,
            'status'     => $mission->status,
        ]);
    }

    public function arrive(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $mission = $this->lifecycle->setArrived(
                $mission,
                $request->user(),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok'         => true,
            'mission_id' => $mission->id,
            'status'     => $mission->status,
        ]);
    }

    public function complete(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $mission = $this->lifecycle->completeMission(
                $mission,
                $request->user(),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok'         => true,
            'mission_id' => $mission->id,
            'status'     => $mission->status,
            'duration_minutes' => $mission->actual_duration_minutes,
        ]);
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

        abort_if(
            ! $isLead && ! $isAssigned,
            403,
            "Vous n'êtes pas assigné à cette mission."
        );
    }

    protected function serialize(Mission $mission, bool $detailed = false): array
    {
        $booking = $mission->booking;

        $base = [
            'id'                       => $mission->id,
            'status'                   => $mission->status,
            'planned_start_at'         => $mission->planned_start_at?->toIso8601String(),
            'actual_start_at'          => $mission->actual_start_at?->toIso8601String(),
            'actual_end_at'            => $mission->actual_end_at?->toIso8601String(),
            'estimated_duration_minutes' => $mission->estimated_duration_minutes,
            'actual_duration_minutes'    => $mission->actual_duration_minutes,
            'booking' => $booking ? [
                'reference'      => $booking->booking_reference,
                'service_name'   => $booking->serviceCatalog?->name,
                'address'        => $booking->address,
                'city'           => $booking->city,
                'postal_code'    => $booking->postal_code,
                'destination_lat'=> $booking->destination_lat,
                'destination_lng'=> $booking->destination_lng,
                'scheduled_date' => $booking->scheduled_date,
                'scheduled_time' => $booking->scheduled_time,
            ] : null,
        ];

        if ($detailed && $booking) {
            $base['booking']['customer_comment'] = $booking->customer_comment ?? null;
            $client = $booking->client ?? $booking->customer ?? null;
            $base['client'] = $client ? [
                'id'    => $client->id,
                'name'  => $client->name,
                'phone' => $client->phone ?? null,
            ] : null;
            $base['client_price'] = $mission->client_price;
            $base['provider_cost'] = $mission->provider_cost;
            $base['checklists_count'] = $mission->checklists->count();
            $base['checklist_items_pending'] = $mission->checklists
                ->flatMap(fn ($c) => $c->items)
                ->where('status', '!=', 'done')
                ->count();
        }

        return $base;
    }
}
