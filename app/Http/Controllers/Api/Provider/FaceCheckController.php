<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\FaceCheck\FaceCheckIncidentService;
use App\Services\FaceCheck\FaceCheckRequirement;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\FaceCheck\FaceCheckSettings;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @group Provider Face Check
 *
 * @authenticated
 *
 * LA SURFACE QUE LE PRESTATAIRE TRAVERSE. Elle est volontairement exclue du middleware
 * `face.verified` : c'est le parcours de remédiation, l'y soumettre enfermerait le compte dans une
 * boucle où l'on exige un contrôle sans jamais laisser le passer.
 *
 * CE QUI N'EN SORT JAMAIS : `next_check_due_at`. Le connaître suffirait à se présenter en personne
 * juste avant, et à prêter son compte le reste du temps. `status` dit s'il faut agir MAINTENANT,
 * jamais quand il faudra agir ensuite.
 */
class FaceCheckController extends Controller
{
    /**
     * L'état du contrôle facial pour le prestataire connecté.
     */
    public function status(
        Request $request,
        FaceCheckGate $gate,
        FaceCheckRequirement $requirement,
        FaceCheckService $service,
        FaceCheckSettings $settings,
    ): JsonResponse {
        $user = $this->prestataire($request);
        $soumis = $requirement->appliesToProvider($user);

        if (! $soumis) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'required' => false,
                    'state' => FaceCheckDecision::OK,
                ],
            ]);
        }

        $verdict = $gate->inspectProvider($user, $this->appareil($request));
        $profil = $service->profileFor($user);

        return response()->json([
            'ok' => true,
            'data' => [
                'required' => true,
                'state' => $verdict->code,
                'message' => $verdict->message,
                'enrolled' => (bool) $profil?->isEnrolled(),
                'blocked' => (bool) $profil?->isBlocked(),
                'block_reason' => $profil?->block_reason,
                'id_match_status' => $profil?->id_match_status,
                'consent_version' => $settings->consentVersion(),
                /*
                 * LE TEXTE DE CONSENTEMENT EST SERVI PAR LE SERVEUR, traduit dans la langue du
                 * prestataire.
                 *
                 * C'est le seul texte du module qui engage juridiquement, et l'application mobile
                 * n'a AUCUN systeme de traduction : le recopier dans le code natif donnerait deux
                 * versions d'un texte relu une seule fois, et c'est celle qu'on n'aurait pas relue
                 * qui s'afficherait. Une seule source -- lang/<code>/face_check.php -- et les deux
                 * surfaces affichent le meme.
                 */
                'consent_text' => __('face_check.consent.text', ['days' => $settings->selfieRetentionDays()]),
                'consent_legal_note' => __('face_check.consent.legal_note'),
                'max_attempts' => $settings->maxAttempts(),
                'liveness_required' => $settings->livenessRequired(),
                'pending_check' => $verdict->checkId,
                'open_incidents' => ProviderFaceIncident::query()
                    ->where('user_id', $user->id)
                    ->open()
                    ->count(),
            ],
        ]);
    }

    /**
     * Enregistre le visage de référence, avec le consentement explicite.
     *
     * @bodyParam image file required Le selfie de référence (jpeg/png, 8 Mo max).
     * @bodyParam consent boolean required L'accord explicite au traitement de la donnée biométrique.
     */
    public function enroll(Request $request, FaceCheckService $service): JsonResponse
    {
        $user = $this->prestataire($request);

        $donnees = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
            'consent' => ['required', 'accepted'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $profil = $service->enroll(
                provider: $user,
                contents: (string) $request->file('image')->get(),
                mimeType: (string) ($request->file('image')->getMimeType() ?: 'image/jpeg'),
                consentement: true,
                contexte: [
                    'ip' => $request->ip(),
                    'device_name' => $donnees['device_name'] ?? $this->appareil($request),
                ],
            );
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error_code' => 'enrolment_refused', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'enrolled' => $profil->isEnrolled(),
                'id_match_status' => $profil->id_match_status,
            ],
        ], 201);
    }

    /**
     * Ouvre un contrôle — ou rend celui qui est déjà ouvert.
     */
    public function start(Request $request, FaceCheckGate $gate, FaceCheckService $service): JsonResponse
    {
        $user = $this->prestataire($request);
        $verdict = $gate->inspectProvider($user, $this->appareil($request));

        if ($verdict->code === FaceCheckDecision::BLOCKED) {
            return response()->json($verdict->toPayload(), 403);
        }

        if ($verdict->code === FaceCheckDecision::ENROLMENT_REQUIRED) {
            return response()->json($verdict->toPayload(), 409);
        }

        /*
         * ON N'OUVRE PAS UN CONTRÔLE QUI N'EST PAS DÛ.
         *
         * Sans cette clause, un client pourrait ouvrir des contrôles à volonté et se fabriquer un
         * historique irréprochable au moment de son choix — exactement la prévisibilité que la
         * cadence aléatoire cherche à supprimer. Le motif vient du serveur, jamais de la requête.
         */
        if ($verdict->allowed()) {
            return response()->json([
                'ok' => true,
                'data' => ['state' => FaceCheckDecision::OK, 'check' => null],
            ]);
        }

        $controle = $service->openCheck(
            $user,
            $verdict->trigger ?? ProviderFaceCheck::TRIGGER_INTERVAL,
            [
                'ip' => $request->ip(),
                'device_name' => $this->appareil($request),
                'app_version' => $request->header('X-App-Version'),
            ],
        );

        return response()->json(['ok' => true, 'data' => $this->presenter($controle)], 201);
    }

    /**
     * Le prestataire présente son visage.
     *
     * @bodyParam image file required Le selfie pris à l'instant.
     */
    public function submit(Request $request, ProviderFaceCheck $faceCheck, FaceCheckService $service): JsonResponse
    {
        $this->autoriser($request, $faceCheck);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        try {
            $faceCheck = $service->submit(
                controle: $faceCheck,
                contents: (string) $request->file('image')->get(),
                mimeType: (string) ($request->file('image')->getMimeType() ?: 'image/jpeg'),
                contexte: [
                    'device_name' => $this->appareil($request),
                    'app_version' => $request->header('X-App-Version'),
                ],
            );
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'error_code' => 'face_check_closed', 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true, 'data' => $this->presenter($faceCheck)]);
    }

    /**
     * Relit un contrôle — le mobile sonde ici tant que le verdict est différé.
     */
    public function show(Request $request, ProviderFaceCheck $faceCheck, FaceCheckService $service): JsonResponse
    {
        $this->autoriser($request, $faceCheck);

        if ($faceCheck->status === ProviderFaceCheck::STATUS_PENDING && $faceCheck->answered_at !== null) {
            $faceCheck = $service->resolvePending($faceCheck);
        }

        return response()->json(['ok' => true, 'data' => $this->presenter($faceCheck)]);
    }

    /**
     * Le prestataire renonce. Ce n'est pas une faute — c'est la répétition qui parle.
     */
    public function abandon(Request $request, ProviderFaceCheck $faceCheck, FaceCheckService $service): JsonResponse
    {
        $this->autoriser($request, $faceCheck);

        $service->abandon($faceCheck);

        return response()->json(['ok' => true, 'data' => $this->presenter($faceCheck->refresh())]);
    }

    /**
     * « Le contrôle ne fonctionne pas. » Ouvre un dossier pour un administrateur.
     *
     * CE GESTE NE DÉBLOQUE RIEN. Un bouton qui accorderait un sursis serait la porte de sortie de
     * quiconque veut éviter le contrôle — et il serait emprunté dès la première semaine.
     */
    public function reportIncident(Request $request, FaceCheckIncidentService $incidents): JsonResponse
    {
        $user = $this->prestataire($request);

        $donnees = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'face_check_id' => ['nullable', 'integer'],
            'diagnostics' => ['nullable', 'array'],
            'diagnostics.platform' => ['nullable', 'string', 'max:32'],
            'diagnostics.os_version' => ['nullable', 'string', 'max:32'],
            'diagnostics.app_version' => ['nullable', 'string', 'max:32'],
            'diagnostics.camera_permission' => ['nullable', 'string', 'max:32'],
            'diagnostics.last_error' => ['nullable', 'string', 'max:500'],
        ]);

        $controle = null;

        if (filled($donnees['face_check_id'] ?? null)) {
            // On ne lit QUE ses propres contrôles : l'identifiant vient du navigateur.
            $controle = ProviderFaceCheck::query()
                ->where('id', $donnees['face_check_id'])
                ->where('user_id', $user->id)
                ->first();
        }

        $incident = $incidents->reportByProvider(
            provider: $user,
            message: $donnees['message'],
            diagnostics: array_merge($donnees['diagnostics'] ?? [], [
                'device_name' => $this->appareil($request),
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]),
            check: $controle,
        );

        return response()->json([
            'ok' => true,
            'data' => [
                'incident_id' => $incident->id,
                'status' => $incident->status,
                // Dit clairement ce que ce geste ne fait pas, pour ne pas le laisser espérer.
                'unblocks' => false,
                'message' => __('face_check.incident.sent_body'),
            ],
        ], 201);
    }

    /**
     * Retrait du consentement — un droit, avec sa conséquence annoncée.
     */
    public function withdrawConsent(Request $request, FaceCheckService $service): JsonResponse
    {
        $user = $this->prestataire($request);

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $service->withdrawConsent($user);

        return response()->json([
            'ok' => true,
            'data' => [
                'message' => __('face_check.consent.withdraw_done'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function presenter(ProviderFaceCheck $controle): array
    {
        return [
            'id' => $controle->id,
            'status' => $controle->status,
            'attempt_number' => $controle->attempt_number,
            'attempts_left' => max(0, app(FaceCheckSettings::class)->maxAttempts() - $controle->attempt_number + 1),
            'failure_reason' => $controle->failure_reason,
            'liveness_result' => $controle->liveness_result,
            'expires_at' => $controle->expires_at?->toIso8601String(),
            'awaiting_provider_decision' => $controle->status === ProviderFaceCheck::STATUS_PENDING
                && $controle->answered_at !== null,
        ];
    }

    private function autoriser(Request $request, ProviderFaceCheck $controle): void
    {
        abort_unless((int) $controle->user_id === (int) $request->user()?->id, 403);
    }

    private function prestataire(Request $request): User
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return $user;
    }

    private function appareil(Request $request): ?string
    {
        $jeton = $request->user()?->currentAccessToken();

        /*
         * `currentAccessToken()` NE REND PAS TOUJOURS UN JETON.
         *
         * Sur une requete authentifiee par la SESSION -- le web, et `Sanctum::actingAs()` dans les
         * tests -- Sanctum rend un `TransientToken` : un objet sans table, sans colonnes, et donc
         * SANS `name`. Lire `->name` dessus leve « Undefined property » et fait tomber la requete
         * entiere. Mesure : quatorze tests de mission et de presence sont tombes d'un coup, tous
         * pour cette seule ligne.
         *
         * Une session n'a pas d'identite d'appareil, et c'est tres bien : elle ne declenche alors
         * aucun controle hors cadence, ce qui est exactement le comportement voulu.
         */
        if (! $jeton instanceof PersonalAccessToken) {
            return null;
        }

        return filled($jeton->name) ? (string) $jeton->name : null;
    }
}
