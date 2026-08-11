<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Services\Missions\OnSite\MissionTimelineService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * LE KIT « SUR PLACE » CÔTÉ PRESTATAIRE — l'état des lieux et les imprévus.
 *
 * `MissionFieldActionController` (web) prend déjà des photos, mais seulement AU MOMENT d'une
 * transition : au démarrage et à la clôture, jointes au formulaire qui valide le code. Sur le
 * terrain, la photo utile est prise quand on la voit — la trace d'humidité derrière le meuble, à
 * la vingtième minute. Ces points d'entrée-ci sont indépendants du cycle de vie, et c'est tout
 * leur intérêt.
 *
 * L'appartenance est vérifiée par le SERVICE, pas ici : elle porte sur l'affectation à la mission,
 * pas sur le droit de lecture, et la même règle doit valoir pour le web comme pour le mobile.
 *
 * @group Mission — sur place
 *
 * @authenticated
 */
class MissionOnSiteController extends Controller
{
    public function __construct(
        protected MissionMediaService $mediaService,
        protected MissionIncidentService $incidentService,
        protected MissionTimelineService $timelineService,
    ) {}

    /**
     * Les clichés déjà pris sur cette mission.
     *
     * @response 200 {"data": [{"id": 4, "type": "before_photo", "label": "Photo avant", "url": "https://…", "taken_at": "2026-08-11T09:02:00+00:00", "fingerprint": "9f2c1a0b77de"}]}
     */
    public function media(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        return response()->json([
            'data' => $this->mediaService
                ->pourLaMission($mission)
                ->map(fn (MissionMedia $m) => $this->mediaService->presenter($m))
                ->values(),
        ]);
    }

    /**
     * Dépose un cliché d'état des lieux.
     *
     * @response 201 {"data": {"id": 4, "type": "before_photo", "label": "Photo avant", "url": "https://…"}}
     * @response 422 {"message": "Format de photo non accepté : application/pdf"}
     */
    public function storeMedia(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'max:12288'],
            'type' => ['required', 'string', 'in:'.implode(',', MissionMedia::typesTerrain())],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $media = $this->mediaService->capture(
                $mission,
                $request->user(),
                $request->file('photo'),
                $data['type'],
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
                isset($data['accuracy_m']) ? (float) $data['accuracy_m'] : null,
                caption: $data['caption'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->mediaService->presenter($media)], 201);
    }

    /**
     * Les imprévus signalés sur cette mission.
     *
     * @response 200 {"data": [{"id": 2, "type": "access_impossible", "label": "Accès impossible", "severity": "high", "status": "open"}]}
     */
    public function incidents(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        return response()->json([
            'data' => $this->incidentService
                ->pourLaMission($mission)
                ->map(fn (MissionIncident $i) => $this->incidentService->presenter($i))
                ->values(),
        ]);
    }

    /**
     * Signale un imprévu — le client est prévenu dans la foulée.
     *
     * @response 201 {"data": {"id": 2, "type": "preexisting_damage", "label": "Dégât préexistant", "notified_at": "2026-08-11T09:05:00+00:00"}}
     */
    public function storeIncident(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', MissionIncident::typesTerrain())],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'photo' => ['nullable', 'file', 'max:12288'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $incident = $this->incidentService->report(
                $mission,
                $request->user(),
                $data['type'],
                $data['description'],
                $request->file('photo'),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->incidentService->presenter($incident)], 201);
    }

    /**
     * Le fil de la mission tel que le prestataire le voit — imprévus internes compris.
     *
     * @response 200 {"mission_id": 12, "status": "started", "progress": {"done": 3, "total": 8, "percent": 38}, "entries": []}
     */
    public function timeline(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        return response()->json($this->timelineService->pour($mission, vueClient: false));
    }
}
