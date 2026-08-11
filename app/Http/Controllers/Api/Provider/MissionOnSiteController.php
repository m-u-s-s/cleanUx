<?php

namespace App\Http\Controllers\Api\Provider;

use App\Services\Missions\OnSite\MissionGuidedChecklistService;
use App\Services\Missions\OnSite\MissionCheckInService;
use Illuminate\Validation\ValidationException;
use App\Services\Missions\OnSite\MissionClosureService;
use App\Services\Missions\OnSite\MissionAccessSheetService;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Missions\OnSite\MissionExtraService;
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
        protected MissionExtraService $extraService,
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionAccessSheetService $accessSheetService,
        protected MissionClosureService $closureService,
        protected MissionCheckInService $checkInService,
        protected MissionGuidedChecklistService $guidedChecklistService,
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
    /**
     * PROPOSER UN SUPPLÉMENT DEPUIS LE TERRAIN (F3).
     *
     * Le prestataire constate sur place ce que le devis ne couvrait pas. Sans ce geste il n'a que
     * deux mauvaises réponses — le faire gratuitement, ou ne pas le faire — et une troisième pire
     * que les deux : s'arranger en espèces, ce qui sort l'argent de la plateforme et le client de
     * toute protection.
     */
    public function storeExtra(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'min:3', 'max:120'],
            'price_cents' => ['required', 'integer', 'min:1', 'max:'.MissionExtraService::MAX_PRICE_CENTS],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_quote_id' => ['nullable', 'integer', 'exists:price_quotes,id'],
        ]);

        try {
            $extra = $this->extraService->propose(
                $mission,
                $request->user(),
                $data['label'],
                (int) $data['price_cents'],
                $data['description'] ?? null,
                isset($data['price_quote_id']) ? (int) $data['price_quote_id'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->extraService->presenter($extra)], 201);
    }

    /**
     * Les suppléments de cette mission, du point de vue du prestataire : il doit savoir ce que le
     * client a accepté avant de faire le travail.
     */
    public function extras(Request $request, Mission $mission): JsonResponse
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());

        return response()->json([
            'data' => $this->extraService->pourLaMission($mission)
                ->map(fn ($extra) => $this->extraService->presenter($extra))
                ->values(),
        ]);
    }

    /**
     * LA FICHE D'ACCÈS AU LIEU (F5) — codes, étage, consignes.
     *
     * Elle ne s'ouvre qu'une fois l'arrivée confirmée : un code d'alarme ou l'emplacement d'une
     * boîte à clés sont les clés du domicile de quelqu'un, et les rendre lisibles dès l'assignation
     * reviendrait à les distribuer à tous ceux qui passent dans la file d'affectation.
     */
    public function accessSheet(Request $request, Mission $mission): JsonResponse
    {
        try {
            $fiche = $this->accessSheetService->pour($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            // 200 et non 403 : ce n'est pas un refus d'accès, c'est un « pas encore ». L'écran doit
            // pouvoir afficher la raison sans traiter la réponse comme une erreur.
            return response()->json(['data' => $this->accessSheetService->verrouillee($e->getMessage())]);
        }

        return response()->json(['data' => $fiche]);
    }

    /**
     * RECUEILLIR LA SIGNATURE DU CLIENT SUR L'ÉCRAN DU PRESTATAIRE (F10).
     *
     * Elle vaut par ce qui l'accompagne : horodatage, empreinte du contenu signé, attribution à un
     * compte. Une case cochée ne prouve rien le jour où le client affirme n'avoir jamais validé.
     *
     * ELLE EST FACULTATIVE, et l'écran doit le refléter : le client n'est pas toujours là, et faire
     * dépendre la clôture de sa présence bloquerait toutes les interventions en son absence.
     */
    public function storeClientSignature(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $data = $request->validate([
            'signature' => ['required', 'string', 'min:8', 'max:200000'],
            'signer_name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $client = $mission->booking?->client;

        if (! $client) {
            return response()->json(['message' => 'Aucun client rattaché à cette mission.'], 422);
        }

        try {
            $document = $this->closureService->signerParLeClient(
                $mission,
                $client,
                $data['signature'],
                $data['signer_name'],
                $request,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['data' => [
            'signed' => true,
            'document_code' => $document->code,
        ]], 201);
    }

    /**
     * ATTESTER SON ARRIVÉE PAR PHOTO QUAND LE CLIENT EST ABSENT (F14).
     *
     * Le code à six chiffres suppose deux personnes face à face. Quand le client travaille et laisse
     * la clé chez la voisine, cette preuve est impossible — et l'intervention se déroulait jusqu'ici
     * hors du dispositif.
     *
     * La photo porte l'horodatage, la position et son empreinte : c'est ce qui sépare un démarrage
     * TRACÉ d'un simple bouton « je suis arrivé » que rien ne contredit.
     */
    public function storeArrivalProof(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $data = $request->validate([
            'photo' => ['required', 'file', 'max:12288'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $media = $this->checkInService->preuveDArriveeSansClient(
                $mission,
                $request->user(),
                $request->file('photo'),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->mediaService->presenter($media)], 201);
    }

    /**
     * Quelle preuve de présence s'applique, et ce qu'il faut savoir si le client est absent.
     *
     * Le prestataire doit le savoir AVANT de sonner : attendre un code qui ne viendra pas fait
     * perdre dix minutes devant une porte, puis repartir.
     */
    public function presenceMode(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $booking = $mission->booking;

        return response()->json(['data' => [
            'mode' => $this->checkInService->modeDePreuve($mission),
            'client_absent' => (bool) ($booking->client_absent ?? false),
            'instructions' => $booking->client_absent_instructions ?? null,
            'backup_contact_name' => $booking->backup_contact_name ?? null,
            'backup_contact_phone' => $booking->backup_contact_phone ?? null,
        ]]);
    }

    /**
     * LE GUIDE PAS-À-PAS (F6) — une étape à la fois.
     *
     * Afficher les vingt suivantes ramènerait à la liste : ce qu'on veut, c'est qu'une personne les
     * mains prises sache quoi faire MAINTENANT, sans lire.
     */
    public function guidedStep(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['data' => [
            'guided' => $this->guidedChecklistService->estGuidee($mission),
            'step' => $this->guidedChecklistService->etapeCourante($mission),
        ]]);
    }

    /** Valider l'étape en cours — et seulement elle. */
    public function completeGuidedStep(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'photo' => ['nullable', 'file', 'max:12288'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        try {
            $this->guidedChecklistService->validerEtape(
                $mission,
                $request->user(),
                (int) $data['item_id'],
                $request->file('photo'),
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'step' => $this->guidedChecklistService->etapeCourante($mission->fresh()),
        ]]);
    }

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
