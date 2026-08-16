<?php

namespace App\Services\FaceCheck;

use App\Jobs\FaceCheck\CompareFaceWithIdDocumentJob;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Notifications\FaceCheck\FaceCheckBlockedNotification;
use App\Notifications\FaceCheck\FaceCheckUnblockedNotification;
use App\Services\FaceCheck\Data\FaceEnrollRequest;
use App\Services\FaceCheck\Data\FaceVerifyRequest;
use App\Services\FaceCheck\Data\FaceVerifyResult;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * TOUT CE QUI ÉCRIT L'ÉTAT DU CONTRÔLE FACIAL PASSE ICI.
 *
 * `FaceCheckGate` lit et rend un verdict ; ce service-ci est le seul à écrire. La séparation n'est
 * pas cosmétique : la porte est appelée depuis des contextes de lecture — un filtre de dispatch,
 * un middleware — où un effet de bord ouvrirait des contrôles à des gens qui ne regardent même pas
 * leur téléphone.
 *
 * Les colonnes de verdict et de garde ne sont pas assignables en masse : elles s'écrivent par
 * `forceFill()`, ici et nulle part ailleurs.
 */
class FaceCheckService
{
    public function __construct(
        private readonly FaceMatchProviderInterface $matcher,
        private readonly FaceImageStore $store,
        private readonly FaceCheckSettings $settings,
        private readonly FaceCheckScheduler $scheduler,
        private readonly FaceCheckIncidentService $incidents,
    ) {}

    public function profileFor(User $provider): ?ProviderFaceProfile
    {
        return ProviderFaceProfile::query()->where('user_id', $provider->id)->first();
    }

    public function ensureProfile(User $provider): ProviderFaceProfile
    {
        return ProviderFaceProfile::query()->firstOrCreate(['user_id' => $provider->id]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Enrôlement
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Enregistre le visage de référence.
     *
     * LE CONSENTEMENT EST UNE CONDITION, PAS UNE CASE À COCHER DÉCORATIVE. Un visage relève de
     * l'article 9 du RGPD : sans consentement explicite, on n'a pas le droit de le traiter, et on
     * refuse donc l'enrôlement plutôt que de l'enregistrer « en attendant ».
     *
     * @param  array<string, mixed>  $contexte  ip, device_name, app_version
     */
    public function enroll(
        User $provider,
        string $contents,
        string $mimeType,
        bool $consentement,
        array $contexte = [],
    ): ProviderFaceProfile {
        if (! $consentement) {
            throw new DomainException(__('face_check.errors.consent_required'));
        }

        if (trim($contents) === '') {
            throw new DomainException(__('face_check.errors.empty_image'));
        }

        $profil = $this->ensureProfile($provider);

        $enrolement = $this->matcher->enroll(new FaceEnrollRequest(
            user: $provider,
            imageContents: $contents,
            mimeType: $mimeType,
            externalApplicantId: $this->applicantId($provider),
        ));

        return DB::transaction(function () use ($profil, $provider, $contents, $mimeType, $contexte, $enrolement) {
            // L'ancienne référence part du disque : on ne garde pas deux visages pour une personne.
            $this->store->forget($profil->reference_path);

            $chemin = $this->store->putReference($provider, $contents, $mimeType);

            $profil->forceFill([
                'status' => ProviderFaceProfile::STATUS_ENROLLED,
                'reference_path' => $chemin,
                'reference_hash' => $this->store->fingerprint($contents),
                'reference_mime' => $mimeType,
                'external_face_id' => $enrolement->externalFaceId,
                'captured_at' => now(),
                'captured_ip_hash' => $this->hachageIp($contexte['ip'] ?? null),
                'captured_device_name' => $contexte['device_name'] ?? null,
                'consent_given_at' => now(),
                'consent_version' => $this->settings->consentVersion(),
                'consent_withdrawn_at' => null,
                'id_match_status' => ProviderFaceProfile::MATCH_PENDING,
                'id_match_score' => null,
                'id_match_checked_at' => null,
                'consecutive_failures' => 0,
                // Un enrôlement neuf ouvre la cadence : le premier contrôle tombera dans la fenêtre.
                'next_check_due_at' => null,
            ])->save();

            $this->scheduler->scheduleNext($profil);

            $this->journaliser('face_check.enrolled', $profil, $provider);

            CompareFaceWithIdDocumentJob::dispatch($profil->id);

            return $profil->refresh();
        });
    }

    /**
     * Le retrait du consentement révoque le visage et ferme la porte.
     *
     * C'est un droit, et sa conséquence doit être annoncée à l'intéressé AVANT qu'il l'exerce :
     * sans visage de référence, il n'y a plus de contrôle possible, donc plus d'intervention chez
     * un client tant qu'un métier soumis figure dans son profil.
     */
    public function withdrawConsent(User $provider): ?ProviderFaceProfile
    {
        $profil = $this->profileFor($provider);

        if ($profil === null) {
            return null;
        }

        $this->store->forget($profil->reference_path);

        $profil->forceFill([
            'status' => ProviderFaceProfile::STATUS_REVOKED,
            'reference_path' => null,
            'reference_hash' => null,
            'consent_withdrawn_at' => now(),
            'blocked_at' => now(),
            'block_reason' => ProviderFaceProfile::BLOCK_CONSENT_WITHDRAWN,
        ])->save();

        $this->journaliser('face_check.consent_withdrawn', $profil, $provider);

        return $profil;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contrôles
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ouvre un contrôle, ou rend celui qui est déjà ouvert.
     *
     * Idempotent volontairement : le mobile et le web peuvent appeler au même instant, et un
     * contrôle par onglet ouvert produirait des abandons en cascade — que le module lirait ensuite
     * comme un évitement. Le module ne doit pas fabriquer les soupçons qu'il mesure.
     *
     * @param  array<string, mixed>  $contexte
     */
    public function openCheck(User $provider, string $trigger, array $contexte = []): ProviderFaceCheck
    {
        $profil = $this->ensureProfile($provider);

        $ouvert = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $profil->id)
            ->where('status', ProviderFaceCheck::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest('requested_at')
            ->first();

        if ($ouvert !== null) {
            return $ouvert;
        }

        $controle = ProviderFaceCheck::create([
            'user_id' => $provider->id,
            'provider_face_profile_id' => $profil->id,
            'triggered_by' => $trigger,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes($this->settings->checkTtlMinutes()),
            'ip_hash' => $this->hachageIp($contexte['ip'] ?? null),
            'device_name' => $contexte['device_name'] ?? null,
            'app_version' => $contexte['app_version'] ?? null,
        ]);

        $this->journaliser('face_check.opened', $controle, $provider);

        return $controle;
    }

    /**
     * Le prestataire présente son visage.
     *
     * @param  array<string, mixed>  $contexte
     */
    public function submit(
        ProviderFaceCheck $controle,
        string $contents,
        string $mimeType,
        array $contexte = [],
    ): ProviderFaceCheck {
        if ($controle->status !== ProviderFaceCheck::STATUS_PENDING) {
            throw new DomainException(__('face_check.errors.check_closed'));
        }

        if ($controle->isExpired()) {
            $this->marquerExpire($controle);

            throw new DomainException(__('face_check.errors.check_expired'));
        }

        $profil = $controle->profile;
        $provider = $controle->user;

        if ($profil === null || $provider === null) {
            throw new DomainException(__('face_check.errors.orphan_check'));
        }

        $reference = $this->store->get($profil->reference_path);

        $resultat = $this->matcher->verify(new FaceVerifyRequest(
            user: $provider,
            probeContents: $contents,
            referenceContents: $reference,
            mimeType: $mimeType,
            externalFaceId: $profil->external_face_id,
            externalApplicantId: $this->applicantId($provider),
        ));

        $chemin = $this->store->putSelfie($controle, $contents, $mimeType);

        $controle->forceFill([
            'selfie_path' => $chemin,
            'answered_at' => now(),
            'match_provider' => $this->matcher->name(),
            'external_check_id' => $resultat->externalCheckId,
            'device_name' => $contexte['device_name'] ?? $controle->device_name,
            'app_version' => $contexte['app_version'] ?? $controle->app_version,
            'raw' => $resultat->raw,
        ])->save();

        return $this->appliquerLeVerdict($controle, $profil, $resultat);
    }

    /**
     * Relit un verdict que le fournisseur n'avait pas rendu.
     */
    public function resolvePending(ProviderFaceCheck $controle): ProviderFaceCheck
    {
        if ($controle->status !== ProviderFaceCheck::STATUS_PENDING || ! filled($controle->external_check_id)) {
            return $controle;
        }

        $profil = $controle->profile;

        if ($profil === null) {
            return $controle;
        }

        $resultat = $this->matcher->fetchVerification((string) $controle->external_check_id);

        if ($resultat->isPending()) {
            return $controle;
        }

        return $this->appliquerLeVerdict($controle, $profil, $resultat);
    }

    public function abandon(ProviderFaceCheck $controle): ProviderFaceCheck
    {
        if ($controle->status !== ProviderFaceCheck::STATUS_PENDING) {
            return $controle;
        }

        $controle->forceFill([
            'status' => ProviderFaceCheck::STATUS_ABANDONED,
            'failure_reason' => 'abandoned_by_provider',
        ])->save();

        $this->journaliser('face_check.abandoned', $controle, $controle->user);
        $this->incidents->noteAbandon($controle);

        return $controle;
    }

    /**
     * UN CONTRÔLE EXPIRÉ N'EST PAS UN ABANDON.
     *
     * Le prestataire n'a peut-être jamais vu l'écran : téléphone en poche, application fermée,
     * notification jamais arrivée. Le compter comme un abandon ferait monter tout seul un signal
     * de fraude contre des gens qui n'ont rien fait — et le premier faux positif suffit à faire
     * cesser de lire les alertes.
     */
    public function expireStale(): int
    {
        $expires = ProviderFaceCheck::query()
            ->where('status', ProviderFaceCheck::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('answered_at')
            ->get();

        foreach ($expires as $controle) {
            $this->marquerExpire($controle);
        }

        return $expires->count();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Gestes d'administrateur
    // ─────────────────────────────────────────────────────────────────────

    public function block(ProviderFaceProfile $profil, string $raison): ProviderFaceProfile
    {
        if ($profil->isBlocked()) {
            return $profil;
        }

        $profil->forceFill([
            'blocked_at' => now(),
            'block_reason' => $raison,
            'unblocked_at' => null,
            'unblocked_by_user_id' => null,
        ])->save();

        $this->journaliser('face_check.blocked', $profil, $profil->user);
        $this->prevenir($profil, new FaceCheckBlockedNotification($raison));

        return $profil;
    }

    /**
     * LA LEVÉE EST UN GESTE D'ADMINISTRATEUR, ET RIEN D'AUTRE NE LA PRODUIT.
     *
     * Ni le temps qui passe, ni un signalement de panne, ni un contrôle réussi : sans quoi il
     * suffirait d'attendre, ou de cliquer sur « ça ne marche pas », pour rouvrir la porte.
     *
     * La levée remet les compteurs à zéro et exige un nouveau contrôle immédiatement : on
     * débloque pour laisser une chance de prouver, pas pour dispenser de prouver.
     */
    public function unblock(ProviderFaceProfile $profil, User $admin, ?string $note = null): ProviderFaceProfile
    {
        $profil->forceFill([
            'blocked_at' => null,
            'block_reason' => null,
            'unblocked_at' => now(),
            'unblocked_by_user_id' => $admin->id,
            'consecutive_failures' => 0,
            'next_check_due_at' => null,
            'review_notes' => $note ?? $profil->review_notes,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->journaliser('face_check.unblocked', $profil, $profil->user);

        /*
         * ON PREVIENT LE PRESTATAIRE. Sans ce message, il decouvrait la levee en reessayant,
         * parfois des jours plus tard : une decision d'administrateur qui change la journee de
         * quelqu'un doit lui parvenir, et le silence transforme une bonne nouvelle en temps perdu.
         */
        $this->prevenir($profil, new FaceCheckUnblockedNotification);

        return $profil;
    }

    public function forceCheck(User $provider, User $admin): ProviderFaceCheck
    {
        $profil = $this->ensureProfile($provider);
        $profil->forceFill(['next_check_due_at' => now()->subMinute()])->save();

        $controle = $this->openCheck($provider, ProviderFaceCheck::TRIGGER_ADMIN_FORCED);

        $this->journaliser('face_check.forced_by_admin', $controle, $provider);

        return $controle;
    }

    /**
     * L'administrateur tranche l'appariement avec la pièce d'identité, et sa décision prime.
     */
    public function overrideIdMatch(
        ProviderFaceProfile $profil,
        User $admin,
        bool $correspond,
        ?string $note = null,
    ): ProviderFaceProfile {
        $profil->forceFill([
            'id_match_status' => $correspond
                ? ProviderFaceProfile::MATCH_MANUAL_OVERRIDE
                : ProviderFaceProfile::MATCH_MISMATCH,
            'id_match_checked_at' => now(),
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $note ?? $profil->review_notes,
        ])->save();

        if ($correspond) {
            $this->unblock($profil, $admin, $note);
        } else {
            $this->block($profil, ProviderFaceProfile::BLOCK_ID_MISMATCH);
        }

        $this->journaliser('face_check.id_match_overridden', $profil, $profil->user);

        return $profil;
    }

    public function revokeReference(ProviderFaceProfile $profil, User $admin, ?string $note = null): ProviderFaceProfile
    {
        $this->store->forget($profil->reference_path);

        $profil->forceFill([
            'status' => ProviderFaceProfile::STATUS_REVOKED,
            'reference_path' => null,
            'reference_hash' => null,
            'external_face_id' => null,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $note ?? $profil->review_notes,
        ])->save();

        $this->journaliser('face_check.reference_revoked', $profil, $profil->user);

        return $profil;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interne
    // ─────────────────────────────────────────────────────────────────────

    private function appliquerLeVerdict(
        ProviderFaceCheck $controle,
        ProviderFaceProfile $profil,
        FaceVerifyResult $resultat,
    ): ProviderFaceCheck {
        if ($resultat->isPending()) {
            // On garde le contrôle ouvert ; `answered_at` est déjà posé, la porte reste fermée.
            return $controle->refresh();
        }

        $vivaciteRatee = $this->settings->livenessRequired()
            && $resultat->liveness === FaceVerifyResult::LIVENESS_FAIL;

        $scoreInsuffisant = $resultat->outcome !== FaceVerifyResult::PASSED
            || ($resultat->score !== null && $resultat->score < $this->settings->matchThreshold());

        if (! $vivaciteRatee && ! $scoreInsuffisant) {
            return $this->reussir($controle, $profil, $resultat);
        }

        $motif = $vivaciteRatee
            ? 'liveness_failed'
            : ($resultat->failureReason ?? 'score_below_threshold');

        if ($vivaciteRatee) {
            $this->incidents->noteLivenessFailure($controle);
        }

        return $this->echouer($controle, $profil, $resultat, $motif);
    }

    private function reussir(
        ProviderFaceCheck $controle,
        ProviderFaceProfile $profil,
        FaceVerifyResult $resultat,
    ): ProviderFaceCheck {
        $controle->forceFill([
            'status' => ProviderFaceCheck::STATUS_PASSED,
            'decision_source' => ProviderFaceCheck::SOURCE_AUTO,
            'score' => $resultat->score,
            'liveness_result' => $resultat->liveness,
            'answered_at' => $controle->answered_at ?? now(),
            'failure_reason' => null,
        ])->save();

        $profil->forceFill([
            'consecutive_failures' => 0,
            'last_check_at' => now(),
        ])->save();

        $this->scheduler->scheduleNext($profil);
        $this->journaliser('face_check.passed', $controle, $controle->user);

        return $controle->refresh();
    }

    private function echouer(
        ProviderFaceCheck $controle,
        ProviderFaceProfile $profil,
        FaceVerifyResult $resultat,
        string $motif,
    ): ProviderFaceCheck {
        $tentative = $controle->attempt_number;

        // Il reste des essais : le contrôle reste ouvert, le prestataire recommence.
        if ($tentative < $this->settings->maxAttempts()) {
            $controle->forceFill([
                'attempt_number' => $tentative + 1,
                'score' => $resultat->score,
                'liveness_result' => $resultat->liveness,
                'failure_reason' => $motif,
                'answered_at' => null,
            ])->save();

            return $controle->refresh();
        }

        $controle->forceFill([
            'status' => ProviderFaceCheck::STATUS_FAILED,
            'decision_source' => ProviderFaceCheck::SOURCE_AUTO,
            'score' => $resultat->score,
            'liveness_result' => $resultat->liveness,
            'failure_reason' => $motif,
            'answered_at' => $controle->answered_at ?? now(),
        ])->save();

        $echecs = $profil->consecutive_failures + 1;

        $profil->forceFill([
            'consecutive_failures' => $echecs,
            'last_check_at' => now(),
        ])->save();

        $this->journaliser('face_check.failed', $controle, $controle->user);
        $this->incidents->noteFailure($controle, $echecs);

        if ($echecs >= $this->settings->failureThreshold()) {
            $this->block($profil, ProviderFaceProfile::BLOCK_FAILED_CHECKS);
        }

        return $controle->refresh();
    }

    private function marquerExpire(ProviderFaceCheck $controle): void
    {
        $controle->forceFill([
            'status' => ProviderFaceCheck::STATUS_EXPIRED,
            'failure_reason' => 'expired',
        ])->save();
    }

    private function applicantId(User $provider): ?string
    {
        $valeur = $provider->providerProfile?->kyc_external_applicant_id;

        return filled($valeur) ? (string) $valeur : null;
    }

    private function hachageIp(?string $ip): ?string
    {
        return filled($ip) ? hash('sha256', (string) $ip) : null;
    }

    /**
     * Prevenir le prestataire -- et ne JAMAIS faire echouer la decision si l'envoi tombe.
     *
     * Le blocage est deja ecrit quand on arrive ici. Laisser remonter une panne de messagerie
     * annulerait la transaction d'un geste de securite pour un e-mail : c'est le mauvais arbitrage,
     * et c'est exactement ce que fait deja `SafetyAlertService`.
     */
    private function prevenir(ProviderFaceProfile $profil, mixed $notification): void
    {
        try {
            $profil->user?->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function journaliser(string $evenement, mixed $sujet, ?User $provider): void
    {
        try {
            ActivityLogger::log($evenement, $sujet, ['provider_user_id' => $provider?->id]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
