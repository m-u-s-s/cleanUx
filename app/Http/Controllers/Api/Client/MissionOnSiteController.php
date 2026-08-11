<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\Concerns\AuthorizesClientBooking;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Services\Missions\OnSite\MissionTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * CE QUI SE PASSE CHEZ MOI, PENDANT QUE ÇA SE PASSE.
 *
 * Le client raisonne en RÉSERVATION — c'est le seul identifiant qu'il ait jamais vu, et le seul
 * que porte son application. La mission est un objet d'exécution interne ; l'exiger de lui
 * l'obligerait à en connaître l'existence, et à la demander d'abord.
 *
 * Une réservation sans mission n'est pas une erreur : elle n'a simplement pas encore commencé. La
 * réponse est alors un fil vide, pas un 404 — un écran de suivi qui affiche « introuvable » avant
 * l'heure du rendez-vous inquiète pour rien.
 *
 * Tout ce qui sort d'ici est filtré sur `client_visible`. Le prestataire documente aussi pour
 * lui-même et pour sa société ; ce n'est pas la même audience.
 *
 * @group Mission — sur place (client)
 *
 * @authenticated
 */
class MissionOnSiteController extends Controller
{
    use AuthorizesClientBooking;

    public function __construct(
        protected MissionMediaService $mediaService,
        protected MissionIncidentService $incidentService,
        protected MissionTimelineService $timelineService,
    ) {}

    /**
     * Le fil de l'intervention : arrivé, démarré, étapes cochées, imprévus, fin estimée.
     *
     * @response 200 {"mission_id": 12, "status": "started", "estimated_end_at": "2026-08-11T11:30:00+00:00", "progress": {"done": 3, "total": 8, "percent": 38}, "entries": [{"kind": "milestone", "label": "Arrivé sur place", "at": "2026-08-11T09:00:00+00:00"}]}
     */
    public function timeline(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json($this->filVide($booking));
        }

        return response()->json($this->timelineService->pour($mission, vueClient: true));
    }

    /**
     * Les photos avant/après, telles qu'elles arrivent.
     *
     * @response 200 {"before": [], "after": [], "incident": []}
     */
    public function media(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['before' => [], 'after' => [], 'incident' => []]);
        }

        $medias = $this->mediaService->pourLaMission($mission, clientSeulement: true);

        /*
         * TROIS PAQUETS, PAS UNE LISTE À PLAT.
         *
         * Le comparateur avant/après est la raison d'être de cette vue : le faire reconstruire par
         * chaque surface (mobile, web, PDF) garantirait trois façons différentes d'apparier une
         * photo d'avant sans photo d'après.
         */
        return response()->json([
            'before' => $this->presenterGroupe($medias, MissionMedia::TYPE_BEFORE_PHOTO),
            'after' => $this->presenterGroupe($medias, MissionMedia::TYPE_AFTER_PHOTO),
            'incident' => $this->presenterGroupe($medias, MissionMedia::TYPE_INCIDENT_PHOTO),
        ]);
    }

    /**
     * Les imprévus signalés — avec de quoi ouvrir un litige sans tout ressaisir.
     *
     * @response 200 {"data": [{"id": 2, "label": "Dégât préexistant", "dispute_prefill": {"category": "damage", "subject": "…", "description": "…"}}]}
     */
    public function incidents(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->incidentService
                ->pourLaMission($mission, clientSeulement: true)
                ->map(fn (MissionIncident $i) => $this->incidentService->presenter($i))
                ->values(),
        ]);
    }

    /**
     * @param  Collection<int, MissionMedia>  $medias
     * @return list<array<string, mixed>>
     */
    private function presenterGroupe($medias, string $type): array
    {
        return $medias
            ->where('media_type', $type)
            ->map(fn (MissionMedia $m) => $this->mediaService->presenter($m))
            ->values()
            ->all();
    }

    private function missionDe(Booking $booking): ?Mission
    {
        return Mission::query()
            ->where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function filVide(Booking $booking): array
    {
        return [
            'mission_id' => null,
            'status' => $booking->status,
            'started_at' => null,
            'estimated_end_at' => null,
            'progress' => ['done' => 0, 'total' => 0, 'percent' => 0],
            'entries' => [],
        ];
    }
}
