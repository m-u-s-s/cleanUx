<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MissionFieldActionController extends Controller
{
    public function offlineSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actions' => ['required', 'array'],
            'actions.*.mission_id' => ['required', 'integer', 'exists:missions,id'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.payload' => ['nullable', 'array'],
            'actions.*.created_at' => ['nullable', 'string'],
        ]);

        foreach ($data['actions'] as $action) {
            $mission = Mission::findOrFail($action['mission_id']);

            $this->authorize('update', $mission);

            \App\Models\MissionEvent::create([
                'mission_id' => $mission->id,
                'user_id' => Auth::id(),
                'event_type' => 'offline_' . $action['type'],
                'title' => 'Action offline synchronisée',
                'description' => 'Action terrain enregistrée hors connexion puis synchronisée.',
                'payload' => $action['payload'] ?? [],
                'happened_at' => $action['created_at'] ?? now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'synced' => count($data['actions']),
        ]);
    }

    public function toggleChecklistItem(
        Request $request,
        \App\Models\MissionChecklistItem $item
    ): JsonResponse {
        $mission = $item->checklist->mission;

        $this->authorize('update', $mission);

        $data = $request->validate([
            'status' => ['required', 'in:pending,done'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $item->notes,
            'completed_by_user_id' => $data['status'] === 'done' ? Auth::id() : null,
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ]);

        $checklist = $item->checklist()->with('items')->first();

        $total = max(1, $checklist->items->count());
        $done = $checklist->items->where('status', 'done')->count();

        $checklist->update([
            'completion_rate' => round(($done / $total) * 100, 2),
            'status' => $done === $total ? 'completed' : 'in_progress',
        ]);

        return response()->json([
            'ok' => true,
            'completion_rate' => $checklist->completion_rate,
            'checklist_status' => $checklist->status,
        ]);
    }

    public function start(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'photos_avant.*' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('photos_avant')) {
            foreach ($request->file('photos_avant') as $photo) {
                $path = $photo->store('missions/photos-avant', 'public');

                $mission->media()->create([
                    'uploaded_by_user_id' => Auth::id(),
                    'media_type' => 'before',
                    'path' => $path,
                    'caption' => 'Photo avant mission',
                    'taken_at' => now(),
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                ]);
            }
        }

        $mission = $service->validateStartCode(
            $mission,
            Auth::user(),
            $data['code'],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
        ]);
    }

    public function enRoute(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
        ]);

        $mission = $service->setEnRoute(
            $mission,
            Auth::user()
        );

        $trackingSession = app(\App\Services\Missions\MissionTrackingService::class)
            ->startToClientTracking(
                $mission,
                Auth::user(),
                (float) $data['lat'],
                (float) $data['lng']
            );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
            'tracking_session_id' => $trackingSession->id,
        ]);
    }

    public function arrived(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $mission = $service->setArrived(
            $mission,
            Auth::user(),
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
        ]);
    }

    public function finish(Request $request, Mission $mission, MissionLifecycleService $service): JsonResponse
    {
        $this->authorize('update', $mission);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'photos_apres.*' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('photos_apres')) {
            foreach ($request->file('photos_apres') as $photo) {
                $path = $photo->store('missions/photos-apres', 'public');

                $mission->media()->create([
                    'uploaded_by_user_id' => Auth::id(),
                    'media_type' => 'after',
                    'path' => $path,
                    'caption' => 'Photo après mission',
                    'taken_at' => now(),
                    'lat' => $data['lat'] ?? null,
                    'lng' => $data['lng'] ?? null,
                ]);
            }
        }

        

        $mission = $service->validateEndCode(
            $mission,
            Auth::user(),
            $data['code'],
            isset($data['lat']) ? (float) $data['lat'] : null,
            isset($data['lng']) ? (float) $data['lng'] : null
        );

        return response()->json([
            'ok' => true,
            'status' => $mission->status,
        ]);
    }
}
