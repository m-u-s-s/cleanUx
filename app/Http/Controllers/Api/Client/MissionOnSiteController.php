<?php

namespace App\Http\Controllers\Api\Client;

use App\Services\Missions\OnSite\MissionCheckInService;
use DomainException;
use App\Services\Missions\OnSite\MissionExtraService;
use App\Models\MissionExtra;
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
        protected MissionExtraService $extraService,
        protected MissionCheckInService $checkInService,
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
     * LES SUPPLÉMENTS PROPOSÉS, ET CELUI QUI ATTEND UNE RÉPONSE (F12).
     */
    public function extras(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->extraService
                ->pourLaMission($mission)
                ->map(fn (MissionExtra $extra) => $this->extraService->presenter($extra))
                ->values(),
        ]);
    }

    /**
     * ACCEPTER EN UN GESTE (F12).
     *
     * Le prestataire est sur place, à l'instant : une réponse qui arrive après son départ ne sert
     * plus à rien. Ce point d'entrée est donc volontairement minimal — pas de formulaire, pas de
     * confirmation en deux temps.
     *
     * L'APPARTENANCE EST VÉRIFIÉE DEUX FOIS, et ce n'est pas une redondance : d'abord que cette
     * réservation est bien celle de la personne, ensuite que ce supplément appartient bien à CETTE
     * mission. Sans la seconde, un identifiant de supplément deviné ferait accepter — et payer — le
     * supplément de quelqu'un d'autre.
     */
    public function approveExtra(Request $request, Booking $booking, MissionExtra $extra): JsonResponse
    {
        return $this->repondreAuSupplement($request, $booking, $extra, accepte: true);
    }

    /** Refuser. Le refus est une réponse, pas une panne : il se conserve et se relit. */
    public function declineExtra(Request $request, Booking $booking, MissionExtra $extra): JsonResponse
    {
        return $this->repondreAuSupplement($request, $booking, $extra, accepte: false);
    }

    private function repondreAuSupplement(
        Request $request,
        Booking $booking,
        MissionExtra $extra,
        bool $accepte,
    ): JsonResponse {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        // Le supplément doit appartenir à la mission de CETTE réservation.
        if ($mission === null || (int) $extra->mission_id !== (int) $mission->id) {
            return response()->json(['message' => 'Supplément introuvable.'], 404);
        }

        try {
            $extra = $accepte
                ? $this->extraService->approve($extra, $request->user())
                : $this->extraService->decline($extra, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->extraService->presenter($extra)]);
    }

    /**
     * DÉCLARER SON ABSENCE (F14).
     *
     * La déclaration vient du CLIENT, jamais du prestataire : si celui qui doit prouver sa présence
     * pouvait décider que la preuve ne s'applique pas, il n'y aurait plus de preuve.
     */
    public function declarerAbsence(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $data = $request->validate([
            'absent' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'backup_contact_name' => ['nullable', 'string', 'max:120'],
            'backup_contact_phone' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $booking = $data['absent']
                ? $this->checkInService->declarerAbsence(
                    $booking,
                    $data['instructions'] ?? null,
                    $data['backup_contact_name'] ?? null,
                    $data['backup_contact_phone'] ?? null,
                )
                : $this->checkInService->annulerAbsence($booking);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'client_absent' => (bool) $booking->client_absent,
            // Le client doit VOIR quelle preuve s'appliquera : c'est ce qui lui évite d'attendre
            // un appel pour un code qu'on ne lui demandera pas.
            'presence_proof' => $booking->client_absent ? 'photo' : 'code',
        ]]);
    }

    /**
     * RÉPONDRE AU PING DE MI-MISSION (F15) — en un geste.
     */
    public function repondreAuPing(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $data = $request->validate([
            'answer' => ['required', 'string', 'in:ok,probleme'],
        ]);

        try {
            $booking = $this->checkInService->repondreAuPing($booking, $request->user(), $data['answer']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'answer' => $booking->checkin_ping_answer,
            'answered_at' => $booking->checkin_ping_answered_at?->toIso8601String(),
        ]]);
    }

    /**
     * LA CLÔTURE GUIDÉE (F16) — un seul flux, dans l'ordre où les choses se décident.
     *
     * Rapport, puis pourboire, puis avis. Ce n'est pas un ordre arbitraire : on ne remercie pas
     * avant de savoir ce qui a été fait, et on ne note pas avant d'avoir décidé si on remercie. Les
     * trois briques existaient chacune de son côté, atteignables depuis trois endroits différents —
     * si bien que la plupart des clients n'en voyaient aucune.
     *
     * CE POINT D'ENTRÉE NE FAIT RIEN, IL DIT CE QUI RESTE À FAIRE. L'écran s'en sert pour n'afficher
     * que les étapes encore ouvertes : proposer de noter une intervention déjà notée fait douter de
     * ce qu'on a validé, et redemander un pourboire déjà versé est franchement gênant.
     */
    public function closureFlow(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['data' => ['available' => false]]);
        }

        $rapport = \App\Models\MissionReport::query()->where('mission_id', $mission->id)->first();

        $pourboireVerse = \App\Models\BookingTip::query()
            ->where('booking_id', $booking->id)
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->exists();

        $avisDonne = \App\Models\Feedback::query()
            ->where('booking_id', $booking->id)
            ->where('client_user_id', $request->user()->id)
            ->exists();

        return response()->json(['data' => [
            'available' => true,
            'mission_id' => $mission->id,
            // Le rapport d'abord : c'est ce qui permet de juger le reste.
            'report' => $rapport ? [
                'number' => $rapport->report_number,
                'checklist_completion_rate' => $rapport->checklist_completion_rate,
                'before_photos_count' => $rapport->before_photos_count,
                'after_photos_count' => $rapport->after_photos_count,
                'incident_count' => $rapport->incident_count,
            ] : null,
            'tip_pending' => ! $pourboireVerse,
            'review_pending' => ! $avisDonne,
            // Rien à faire : l'écran doit pouvoir fermer le flux plutôt que d'afficher une page
            // vide avec un bouton « suivant ».
            'completed' => $rapport !== null && $pourboireVerse && $avisDonne,
        ]]);
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
