<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11 — Endpoints API mobile pour accept/decline d'une mission.
 *
 * Routes :
 *   GET    /api/provider/assignments/inbox        Mes offres en attente
 *   POST   /api/provider/assignments/{id}/accept  Accepter
 *   POST   /api/provider/assignments/{id}/decline Refuser
 *   GET    /api/provider/assignments/{id}         Détail d'une offre
 *
 * Sécurité :
 *   - Sanctum auth
 *   - L'assignment doit appartenir au user authentifié
 */
class ProviderMissionAssignmentController extends Controller
{
    public function __construct(
        protected MissionDispatchService $dispatch,
    ) {}

    /**
     * Liste des offres en attente d'accept/decline pour le prestataire.
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();

        $assignments = MissionAssignment::query()
            ->where('user_id', $user->id)
            ->where('assignment_status', 'assigned')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->with([
                'mission:id,booking_id,planned_start_at,status,start_lat,start_lng,end_lat,end_lng',
                'mission.booking:id,booking_reference,address,city,postal_code,service_catalog_id,scheduled_date,scheduled_time,booking_mode,priority',
                'mission.booking.serviceCatalog:id,name',
            ])
            ->orderBy('expires_at')
            ->get();

        return response()->json([
            'ok'    => true,
            'count' => $assignments->count(),
            'data'  => $assignments->map(fn ($a) => $this->serializeAssignment($a))->all(),
        ]);
    }

    /**
     * Détail d'une offre.
     */
    public function show(Request $request, MissionAssignment $assignment): JsonResponse
    {
        $this->authorizeOwnership($request, $assignment);

        $assignment->load([
            'mission:id,booking_id,planned_start_at,status,start_lat,start_lng,end_lat,end_lng,estimated_duration_minutes,client_price',
            'mission.booking:id,booking_reference,address,city,postal_code,service_catalog_id,scheduled_date,scheduled_time,booking_mode,priority,customer_comment',
            'mission.booking.serviceCatalog:id,name',
        ]);

        return response()->json([
            'ok'   => true,
            'data' => $this->serializeAssignment($assignment, detailed: true),
        ]);
    }

    /**
     * Accepter l'offre.
     */
    public function accept(Request $request, MissionAssignment $assignment): JsonResponse
    {
        $this->authorizeOwnership($request, $assignment);

        try {
            $assignment = $this->dispatch->accept($assignment);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok'              => true,
            'assignment_id'   => $assignment->id,
            'status'          => $assignment->assignment_status,
            'response_seconds'=> $assignment->response_seconds,
        ]);
    }

    /**
     * Refuser l'offre.
     */
    public function decline(Request $request, MissionAssignment $assignment): JsonResponse
    {
        $this->authorizeOwnership($request, $assignment);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $next = $this->dispatch->decline($assignment, $data['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok'                  => true,
            'assignment_id'       => $assignment->id,
            'status'              => 'declined',
            'reassigned_to_other' => $next !== null,
        ]);
    }

    protected function authorizeOwnership(Request $request, MissionAssignment $assignment): void
    {
        abort_if(
            (int) $assignment->user_id !== (int) $request->user()->id,
            403,
            'Cette offre ne vous est pas destinée.'
        );
    }

    protected function serializeAssignment(MissionAssignment $a, bool $detailed = false): array
    {
        $mission = $a->mission;
        $booking = $mission?->booking;

        $base = [
            'id'                   => $a->id,
            'mission_id'           => $a->mission_id,
            'assignment_status'    => $a->assignment_status,
            'assigned_at'          => $a->assigned_at?->toIso8601String(),
            'expires_at'           => $a->expires_at?->toIso8601String(),
            'remaining_seconds'    => $a->expires_at
                ? max(0, (int) now()->diffInSeconds($a->expires_at, false))
                : null,
            'mission' => $mission ? [
                'id'                          => $mission->id,
                'planned_start_at'            => $mission->planned_start_at?->toIso8601String(),
                'estimated_duration_minutes'  => $mission->estimated_duration_minutes ?? null,
            ] : null,
            'booking' => $booking ? [
                'reference'      => $booking->booking_reference,
                'service_name'   => $booking->serviceCatalog?->name,
                'address'        => $booking->address,
                'city'           => $booking->city,
                'postal_code'    => $booking->postal_code,
                'scheduled_date' => $booking->scheduled_date,
                'scheduled_time' => $booking->scheduled_time,
                'mode'           => $booking->booking_mode,
                'priority'       => $booking->priority,
            ] : null,
        ];

        if ($detailed && $booking) {
            $base['booking']['customer_comment'] = $booking->customer_comment ?? null;
            $base['mission']['client_price'] = $mission->client_price ?? null;
            $base['mission']['start_lat'] = $mission->start_lat ?? null;
            $base['mission']['start_lng'] = $mission->start_lng ?? null;
            $base['mission']['end_lat'] = $mission->end_lat ?? null;
            $base['mission']['end_lng'] = $mission->end_lng ?? null;
        }

        return $base;
    }
}
