<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\Concerns\AuthorizesClientBooking;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\Feedback;
use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionExtra;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\MissionQuoteRevision;
use App\Models\MissionReport;
use App\Services\Client\Calendar\BookingRescheduleService;
use App\Services\Missions\HourlyExtensionService;
use App\Services\Missions\MissionDelayService;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Services\Missions\MissionTodoService;
use App\Services\Missions\OnSite\MissionCheckInService;
use App\Services\Missions\OnSite\MissionExtraService;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Services\Missions\OnSite\MissionTimelineService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

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
        protected HourlyExtensionService $extensionService,
        protected MissionTodoService $todoService,
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
     * PROLONGER — acheter du temps en plus, avant ou pendant l'intervention.
     *
     * LE CLIENT DÉCIDE SEUL, sans passer par le prestataire : c'est son argent et son logement.
     * Faire transiter la demande par une acceptation ferait perdre les minutes qui comptent — la
     * fenêtre se ferme à la fin de la franchise.
     *
     * LE REFUS EST UNE RÉPONSE, PAS UNE PANNE. `422` avec un message lisible : « la durée maximale
     * est atteinte », « le temps supplémentaire est déjà facturé ». Un `500` laisserait le client
     * appuyer trois fois sur un bouton qui ne dit rien.
     *
     * @response 200 {"clock": {"applies": true, "purchased_minutes": 240, "deadline_at": "..."}}
     */
    public function prolonger(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $donnees = $request->validate([
            /*
             * BORNE HAUTE ICI AUSSI, et pas seulement dans le service : sans elle, un entier
             * démesuré traverserait la validation avant d'être refusé plus loin — et un entier
             * négatif tenterait une RÉDUCTION du temps acheté, ce que personne n'a autorisé.
             */
            'additional_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        try {
            $horloge = $this->extensionService->prolonger(
                $booking,
                (int) $donnees['additional_minutes'],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'clock' => $horloge,
            'extension' => $this->extensionService->etatDeLaProlongation($booking->refresh()),
        ]);
    }

    /**
     * MA LISTE DE TÂCHES — ce que je veux qu'on fasse chez moi.
     *
     * Elle porte AUSSI la fenêtre et les suggestions : l'écran doit pouvoir afficher le minuteur
     * et proposer le gabarit sans un second aller-retour. Une seule lecture, un seul instant.
     *
     * @response 200 {"ok": true, "engine": "domicile", "window": {"open": true, "closes_at": null, "minutes_left": null, "reason": null}, "items": [], "suggestions": ["Vérifier accès client"]}
     */
    public function todo(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            // Pas encore de mission : la liste n'a pas d'objet, et le dire vaut mieux qu'un 404 —
            // le client consulte souvent son suivi avant que le prestataire ne soit assigné.
            return response()->json([
                'ok' => true,
                'engine' => null,
                'window' => ['open' => false, 'closes_at' => null, 'minutes_left' => 0,
                    'reason' => 'L’intervention n’est pas encore ouverte.'],
                'items' => [],
                'suggestions' => [],
            ]);
        }

        return response()->json(['ok' => true] + $this->todoService->pourLeClient($mission));
    }

    /**
     * AJOUTER UNE TÂCHE. Elle conditionnera la clôture du prestataire — c'est dit à l'écran avant
     * l'écriture, et redit ici par la réponse.
     */
    public function ajouterUneTache(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $donnees = $request->validate([
            'label' => ['required', 'string', 'max:191'],
        ]);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['message' => 'L’intervention n’est pas encore ouverte.'], 422);
        }

        try {
            $this->todoService->ajouter($mission, $request->user(), (string) $donnees['label']);
        } catch (DomainException $e) {
            // 422 et le MESSAGE DU DOMAINE : « la liste est figée depuis 10:30 » explique ce qu'un
            // « une erreur est survenue » laisserait deviner, et fait réessayer pour rien.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $this->todoService->pourLeClient($mission->refresh()));
    }

    /** RETIRER UNE TÂCHE — la sienne, et pas encore faite. */
    public function retirerUneTache(Request $request, Booking $booking, MissionChecklistItem $item): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);

        if ($mission === null) {
            return response()->json(['message' => 'L’intervention n’est pas encore ouverte.'], 422);
        }

        try {
            $this->todoService->retirer($mission, $request->user(), $item);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $this->todoService->pourLeClient($mission->refresh()));
    }

    /**
     * LE NOUVEAU DEVIS QUE LE PRESTATAIRE PROPOSE — vu du salon.
     *
     * Les DEUX totaux, ligne à ligne, avec le nom du code de réduction s'il y en a un. Sans cela,
     * le client verrait un chiffre plus élevé sans comprendre où est passée sa remise, et refuserait
     * par réflexe.
     */
    public function revisionDeDevis(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $mission = $this->missionDe($booking);
        $revision = $mission === null ? null : app(MissionQuoteRevisionService::class)->vivante($mission);

        return response()->json([
            'ok' => true,
            'revision' => $revision === null ? null : $this->presenterLaRevision($revision),
        ]);
    }

    /** Le client accepte : le complément est autorisé, puis le devis est réécrit. Dans cet ordre. */
    public function accepterLaRevision(
        Request $request,
        Booking $booking,
        MissionQuoteRevision $revision,
    ): JsonResponse {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        abort_if((int) $revision->booking_id !== (int) $booking->id, 404);

        $donnees = $request->validate([
            'payment_method_id' => ['nullable', 'string', 'max:191'],
        ]);

        try {
            $acceptee = app(MissionQuoteRevisionService::class)->accepter(
                $revision,
                $request->user(),
                $donnees['payment_method_id'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'revision' => $this->presenterLaRevision($acceptee)]);
    }

    /**
     * Le client refuse — et DIT ce qu'il veut ensuite.
     *
     * `continue` garde la prestation au prix d'origine, `stop` l'arrête. Le serveur ne choisit pas
     * à sa place : les deux issues n'ont pas le même coût pour lui.
     */
    public function refuserLaRevision(
        Request $request,
        Booking $booking,
        MissionQuoteRevision $revision,
    ): JsonResponse {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        abort_if((int) $revision->booking_id !== (int) $booking->id, 404);

        $donnees = $request->validate([
            'decision' => ['required', 'string', 'in:continue,stop'],
        ]);

        try {
            $refusee = app(MissionQuoteRevisionService::class)->refuser(
                $revision,
                $request->user(),
                (string) $donnees['decision'],
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'revision' => $this->presenterLaRevision($refusee),
            // L'écran doit savoir s'il enchaîne sur l'annulation, sans la déclencher lui-même.
            'must_cancel' => $refusee->doitEtreAnnulee(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presenterLaRevision(MissionQuoteRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'status' => $revision->status,
            'awaiting_client' => $revision->attendLeClient(),
            'original_total' => round($revision->original_total_cents / 100, 2),
            'revised_total' => round($revision->revised_total_cents / 100, 2),
            'currency' => $revision->currency,
            'breakdown' => $revision->discount_breakdown,
            'reason_text' => $revision->reason_text,
            'evidence_media_ids' => $revision->evidence_media_ids,
            'window_closes_at' => $revision->window_closes_at->toIso8601String(),
        ];
    }

    /**
     * LA CONSIGNE DE DERNIÈRE MINUTE — « le digicode a changé ce matin ».
     *
     * Elle se pose pendant que le prestataire est en route, et elle prime sur les consignes du
     * carnet parce qu'elle est la plus récente. Elle ne les REMPLACE pas : écrire dans le carnet
     * ferait lire un code du jour à quelqu'un d'autre la semaine suivante.
     *
     * Une chaîne vide EFFACE la consigne — le client s'est trompé, ou la situation est revenue à
     * la normale, et lui refuser l'effacement le laisserait avec une fausse consigne affichée.
     *
     * @bodyParam note string La consigne, ou une chaîne vide pour l'effacer. Example: Le digicode est 4589.
     */
    public function consigneDAcces(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $donnees = $request->validate([
            'note' => ['present', 'nullable', 'string', 'max:500'],
        ]);

        $note = trim((string) ($donnees['note'] ?? ''));

        $booking->forceFill([
            'live_access_note' => $note !== '' ? $note : null,
            'live_access_note_at' => $note !== '' ? now() : null,
        ])->save();

        return response()->json([
            'ok' => true,
            'live_note' => $booking->live_access_note,
            'live_note_at' => $booking->live_access_note_at?->toIso8601String(),
        ]);
    }

    /**
     * LE MINUTEUR DE RETARD, VU DU CLIENT.
     *
     * Sur son propre chemin, et pas greffé sur le suivi GPS : `tracking` rend `null` tant
     * qu'aucune session n'est ouverte, et un prestataire qui n'est jamais parti est exactement le
     * cas où le retard compte le plus. Le suivi aurait donc été muet précisément quand il fallait
     * parler.
     */
    public function retard(Request $request, Booking $booking, MissionDelayService $retards): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        return response()->json(['data' => $retards->etat($booking)]);
    }

    /**
     * DÉCALER L'INTERVENTION — la deuxième des trois issues.
     *
     * Elle réutilise `BookingRescheduleService`, qui portait déjà l'autorisation, la validation et
     * l'historique. Ce service n'était atteignable QUE par le glisser-déposer du calendrier web :
     * un client mobile devant un prestataire en retard n'avait donc le choix qu'entre attendre et
     * annuler, alors que le décalage existait à quelques lignes de là.
     */
    public function reprogrammer(Request $request, Booking $booking, BookingRescheduleService $reprogrammation): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $donnees = $request->validate([
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $booking = $reprogrammation->reschedule(
                $request->user(),
                $booking,
                Carbon::parse($donnees['date']),
                $donnees['time'] ?? null,
                $donnees['reason'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
        ]);
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

        $rapport = MissionReport::query()->where('mission_id', $mission->id)->first();

        $pourboireVerse = BookingTip::query()
            ->where('booking_id', $booking->id)
            ->whereNotIn('status', ['failed', 'cancelled'])
            ->exists();

        $avisDonne = Feedback::query()
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
