<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Services\Inventory\InventoryService;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Missions\OnSite\MissionAccessSheetService;
use App\Services\Missions\OnSite\MissionCheckInService;
use App\Services\Missions\OnSite\MissionChecklistService;
use App\Services\Missions\OnSite\MissionClosureService;
use App\Services\Missions\OnSite\MissionExtraService;
use App\Services\Missions\OnSite\MissionGuidedChecklistService;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Services\Missions\OnSite\MissionTimelineService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * LE KIT « SUR PLACE » CÔTÉ PRESTATAIRE — l'état des lieux et les imprévus.
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
        protected MissionChecklistService $checklistService,
        protected InventoryService $inventoryService,
    ) {}

    /** LES TÂCHES QUI BLOQUENT LA CLÔTURE, enfin lisibles depuis le mobile. */
    public function checklist(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['data' => $this->checklistService->pour($mission)]);
    }

    /** Cocher ou décocher une tâche de la checklist de mission. */
    public function toggleChecklistItem(Request $request, Mission $mission, MissionChecklistItem $item): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        // LA TÂCHE DOIT APPARTENIR À CETTE MISSION.
        if ((int) $item->checklist->mission_id !== (int) $mission->id) {
            return response()->json(['message' => 'Cette tâche n’appartient pas à cette mission.'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:pending,done'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->checklistService->basculer($item, $data['status'], $data['notes'] ?? null, $request->user());

        // La liste complète est renvoyée : l'écran doit pouvoir redire ce qui bloque encore sans
        // second appel, au moment précis où le prestataire s'apprête à clôturer.
        return response()->json(['data' => $this->checklistService->pour($mission->fresh())]);
    }

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
    /** PROPOSER UN SUPPLÉMENT DEPUIS LE TERRAIN (F3). */
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

    /** Les suppléments de cette mission, du point de vue du prestataire : il doit savoir ce que le client a accepté avant de faire le travail. */
    public function extras(Request $request, Mission $mission): JsonResponse
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());

        return response()->json([
            'data' => $this->extraService->pourLaMission($mission)
                ->map(fn ($extra) => $this->extraService->presenter($extra))
                ->values(),
        ]);
    }

    /** LA FICHE D'ACCÈS AU LIEU (F5) — codes, étage, consignes. */
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

    /** RECUEILLIR LA SIGNATURE DU CLIENT SUR L'ÉCRAN DU PRESTATAIRE (F10). */
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

    /** ATTESTER SON ARRIVÉE PAR PHOTO QUAND LE CLIENT EST ABSENT (F14). */
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

    /** Quelle preuve de présence s'applique, et ce qu'il faut savoir si le client est absent. */
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

    /** LE GUIDE PAS-À-PAS (F6) — une étape à la fois. */
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

    /** LES CONSOMMABLES DISPONIBLES POUR CETTE MISSION (F7). */
    public function consumables(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $organisationId = (int) ($mission->provider_organization_id ?? 0);

        if ($organisationId <= 0) {
            return response()->json(['data' => []]);
        }

        $articles = InventoryItem::query()
            ->where('organization_account_id', $organisationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $articles->map(fn ($article) => [
            'id' => $article->id,
            'name' => $article->name,
            'unit' => $article->unit,
            'quantity' => $article->quantity,
            'is_billable' => (bool) $article->is_billable,
        ])->values()]);
    }

    /** DÉCLARER CE QU'ON A CONSOMMÉ SUR PLACE (F7). */
    public function storeConsumable(Request $request, Mission $mission): JsonResponse
    {
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $data = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $article = InventoryItem::query()->findOrFail((int) $data['inventory_item_id']);

        // Le stock appartient à la société qui exécute la mission : sans ce contrôle, un
        // identifiant deviné ponctionnerait le magasin d'une société concurrente.
        if ((int) $article->organization_account_id !== (int) ($mission->provider_organization_id ?? 0)) {
            return response()->json(['message' => 'Cet article n’appartient pas à votre société.'], 403);
        }

        try {
            $article = $this->inventoryService->consommer(
                $article,
                (int) $data['quantity'],
                $request->user(),
                $mission,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'inventory_item_id' => $article->id,
            'remaining' => $article->quantity,
        ]], 201);
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
