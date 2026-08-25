<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionQuoteRevision;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionReinforcementService;
use App\Services\Missions\QuoteRevisionPricing;
use App\Services\Missions\QuoteRevisionWindow;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** LE NOUVEAU DEVIS, CÔTÉ TERRAIN. */
class ProviderQuoteRevisionController extends Controller
{
    public function __construct(
        protected MissionQuoteRevisionService $revisions,
        protected QuoteRevisionWindow $fenetre,
        protected QuoteRevisionPricing $tarification,
    ) {}

    /**
     * L'état de la révision sur cette mission.
     *
     * @response 200 {"ok": true, "window": {"open": true, "closes_at": null, "reason": null}, "revision": null}
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $vivante = $this->revisions->vivante($mission);

        return response()->json([
            'ok' => true,
            /*
             * LA DEVISE VOYAGE AVEC LA FENETRE, pas seulement avec la revision.
             *
             * Le formulaire s'affiche quand il n'y a PAS encore de revision : sa devise ne
             * pouvait donc pas venir d'elle, et l'ecran ecrivait « (EUR) » en dur sur son
             * champ de saisie. Un prestataire marocain annoncait un prix dans une monnaie
             * que son client ne paiera pas.
             */
            'window' => $this->fenetre->etat($mission) + [
                'currency' => strtoupper((string) ($mission->booking?->currency ?: 'EUR')),
            ],
            'revision' => $vivante === null ? null : $this->presenter($vivante),
        ]);
    }

    /** Simuler : « si j'annonce ce prix de service, que paiera le client ? */
    public function simulate(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $donnees = $request->validate([
            'service_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        $reservation = $mission->booking;

        if ($reservation === null) {
            return response()->json(['message' => 'Cette mission n’a pas de réservation.'], 422);
        }

        return response()->json([
            'ok' => true,
            'quote' => $this->tarification->recalculer($reservation, (int) $donnees['service_cents']),
        ]);
    }

    /**
     * Proposer un prix révisé.
     *
     * @bodyParam service_cents integer required Le prix du SERVICE en centimes, hors remises. Example: 30000
     * @bodyParam reason_text string required Ce qui justifie ce prix. Example: Deux cents mètres carrés, pas vingt.
     * @bodyParam media_ids integer[] required Au moins une photo déjà envoyée. Example: [12]
     *
     * @response 422 {"message": "Ajoutez au moins une photo : sans preuve, le client doit vous croire sur parole."}
     */
    public function store(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $donnees = $request->validate([
            'service_cents' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason_text' => ['required', 'string', 'max:2000'],
            'reason_code' => ['nullable', 'string', 'max:64'],
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer'],
        ]);

        try {
            $revision = $this->revisions->proposer(
                $mission,
                $request->user(),
                (int) $donnees['service_cents'],
                (string) $donnees['reason_text'],
                array_map('intval', $donnees['media_ids']),
                (string) ($donnees['reason_code'] ?? 'ecart_constate'),
            );
        } catch (DomainException $e) {
            // 422 et le MESSAGE DU DOMAINE : « vous avez commencé l'intervention » dit au
            // prestataire quel geste employer à la place. Un « erreur » le ferait réessayer.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'revision' => $this->presenter($revision)], 201);
    }

    /** Retirer sa proposition — un geste honnête, qui doit rester possible. */
    public function destroy(Request $request, Mission $mission, MissionQuoteRevision $revision): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        abort_if((int) $revision->mission_id !== (int) $mission->id, 404);

        try {
            $retiree = $this->revisions->retirer($revision, $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'revision' => $this->presenter($retiree)]);
    }

    /**
     * DEMANDER DU RENFORT — la troisième issue, celle qui manquait.
     *
     * @bodyParam reason string required Ce qui justifie le renfort. Example: Deux cents mètres carrés à deux.
     * @bodyParam people integer Nombre de personnes demandées. Example: 1
     *
     * @response 201 {"ok": true, "reinforcement": {"id": 3, "status": "open"}}
     */
    public function renfort(Request $request, Mission $mission): JsonResponse
    {
        $this->authorizeProvider($request, $mission);

        $donnees = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'people' => ['nullable', 'integer', 'min:1', 'max:20'],
            'minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
        ]);

        try {
            $demande = app(MissionReinforcementService::class)->demander(
                $mission,
                $request->user(),
                (string) $donnees['reason'],
                (int) ($donnees['people'] ?? 1),
                (int) ($donnees['minutes'] ?? 60),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'reinforcement' => [
                'id' => $demande->id,
                'status' => $demande->status,
                'required_people' => $demande->required_people,
                'reason' => $demande->reason,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presenter(MissionQuoteRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'status' => $revision->status,
            'original_total_cents' => $revision->original_total_cents,
            'revised_total_cents' => $revision->revised_total_cents,
            'top_up_cents' => $revision->complementCents(),
            'currency' => $revision->currency,
            'breakdown' => $revision->discount_breakdown,
            'reason_code' => $revision->reason_code,
            'reason_text' => $revision->reason_text,
            'awaiting_client' => $revision->attendLeClient(),
            'client_decision' => $revision->client_decision,
            'window_closes_at' => $revision->window_closes_at->toIso8601String(),
            'last_error' => $revision->last_error,
        ];
    }

    /** La même garde que l'annulation prestataire : le responsable, ou quelqu'un d'affecté et réellement en cours. */
    protected function authorizeProvider(Request $request, Mission $mission): void
    {
        $userId = $request->user()->id;

        $isLead = (int) $mission->lead_provider_user_id === (int) $userId;
        $isAssigned = $mission->assignments()
            ->where('user_id', $userId)
            ->whereIn('assignment_status', ['accepted', 'en_route', 'arrived'])
            ->exists();

        abort_if(! $isLead && ! $isAssigned, 403, 'Vous n’êtes pas assigné à cette mission.');
    }
}
