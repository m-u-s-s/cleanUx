<?php

namespace App\Http\Controllers\Api\Provider;

use App\Events\Realtime\MissionLiveEta;
use App\Events\Realtime\MissionLivePosition;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Realtime\RealtimeBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints "live" pour un prestataire en mission active.
 *
 *   - POST /provider/missions/{mission}/live/position
 *   - POST /provider/missions/{mission}/live/eta
 *
 * Différent de l'historique mission tracking (sessions GPS) :
 *   ici on broadcast just-in-time pour le client en wait, sans
 *   nécessairement persister chaque ping (le ledger broadcast_events suffit).
 */
class MissionLiveTrackingController extends Controller
{
    public function __construct(protected RealtimeBroadcastService $realtime)
    {
    }

    public function pushPosition(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeAsProvider($request, $mission);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'sequence' => ['nullable', 'string', 'max:64'],
        ]);

        $event = new MissionLivePosition(
            mission: $mission,
            latitude: (float) $data['lat'],
            longitude: (float) $data['lng'],
            accuracyMeters: isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
            headingDegrees: isset($data['heading']) ? (float) $data['heading'] : null,
            providerUserId: $request->user()->id,
            sequence: $data['sequence'] ?? null,
        );

        $ledger = $this->realtime->publish($event);

        return response()->json([
            'ok' => true,
            'broadcast_id' => $ledger?->id,
            'channel' => 'mission.' . $mission->id,
        ]);
    }

    public function pushEta(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeAsProvider($request, $mission);

        $data = $request->validate([
            'eta_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'sequence' => ['nullable', 'string', 'max:64'],
        ]);

        $event = new MissionLiveEta(
            mission: $mission,
            etaMinutes: (int) $data['eta_minutes'],
            latitude: isset($data['lat']) ? (float) $data['lat'] : null,
            longitude: isset($data['lng']) ? (float) $data['lng'] : null,
            sequence: $data['sequence'] ?? null,
        );

        $ledger = $this->realtime->publish($event);

        return response()->json([
            'ok' => true,
            'broadcast_id' => $ledger?->id,
            'channel' => 'mission.' . $mission->id,
        ]);
    }

    protected function authorizeAsProvider(Request $request, Mission $mission): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        $isLead = (int) ($mission->lead_provider_user_id ?? 0) === (int) $user->id
               || (int) ($mission->lead_employee_id ?? 0) === (int) $user->id;

        $isAssigned = $mission->assignments()
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($isLead || $isAssigned, 403, 'Not assigned to this mission.');
    }
}
