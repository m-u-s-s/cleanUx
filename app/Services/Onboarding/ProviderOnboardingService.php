<?php

namespace App\Services\Onboarding;

use App\Enums\ProviderType;
use App\Models\OnboardingProgress;
use App\Models\OnboardingStepCompletion;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use App\Services\OnboardingV2\OnboardingEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Phase 14 — Service de gestion de l'onboarding prestataire. */
class ProviderOnboardingService
{
    public const STEP_PROFILE_BASICS = 0;

    public const STEP_IDENTITY = 1;

    public const STEP_TAX = 2;

    public const STEP_INSURANCE = 3;

    public const STEP_SKILLS = 4;

    public const STEP_STRIPE_CONNECT = 5;

    public const STEP_READY = 6;

    /** Crée un ProviderProfile vide pour un user qui s'inscrit comme prestataire. */
    public function startOnboarding(User $user): ProviderProfile
    {
        return ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                // LA VALEUR CANONIQUE, celle qu'écrit déjà l'inscription par l'API (`ApiAuthController`).
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'pending',
                'verification_status' => 'pending',
                'onboarding_step' => self::STEP_PROFILE_BASICS,
                'commission_rate' => 20.00,
            ]
        );
    }

    /** Étape 0 — Infos basiques (nom, photo, bio). */
    public function setProfileBasics(User $user, array $data, ?UploadedFile $photo = null): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        $update = [];
        if (isset($data['bio'])) {
            $update['bio'] = $data['bio'];
        }

        if ($photo !== null) {
            // Suppression de l'ancienne photo si présente
            if ($profile->photo_path) {
                Storage::disk('public')->delete($profile->photo_path);
            }
            $update['photo_path'] = $photo->store("providers/{$user->id}/photo", 'public');
        }

        // Update name + phone côté User
        if (! empty($data['name'])) {
            $user->name = $data['name'];
        }
        if (! empty($data['phone'])) {
            $user->phone = $data['phone'];
        }
        $user->save();

        if (! empty($update)) {
            $profile->fill($update)->save();
        }

        // Avance la step si on n'a pas encore dépassé
        $this->advanceStepIfNeeded($profile, self::STEP_PROFILE_BASICS);

        return $profile->fresh();
    }

    /** Étape 1, 3, 6, 7 — Upload d'un document. */
    /**
     * @param  string|null  $expiresAt  La date de fin de validité, quand la pièce en porte une —
     *                                  permis, assurance, contrôle technique. La colonne
     *                                  `expires_at` EXISTAIT et n'était écrite par personne :
     *                                  `isExpired()` n'était donc jamais vrai, et une pièce
     *                                  d'identité approuvée le restait indéfiniment.
     * @param  array<string, mixed>  $metadata  Ce que la pièce atteste — la plaque déclarée sur une
     *                                          assurance, par exemple. Même histoire : la colonne
     *                                          existait, castée, et vide.
     */
    public function uploadDocument(
        User $user,
        string $type,
        UploadedFile $file,
        ?string $expiresAt = null,
        array $metadata = [],
    ): ProviderOnboardingDocument {
        $this->validateDocumentType($type);

        $profile = $this->ensureProfile($user);

        // Si ce type est déjà uploadé, on le remplace (et supprime le fichier ancien)
        $existing = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->where('document_type', $type)
            ->latest()
            ->first();

        if ($existing && $existing->file_path) {
            Storage::disk('private')->delete($existing->file_path);
        }

        $path = $file->store("providers/{$user->id}/onboarding/{$type}", 'private');

        $document = DB::transaction(function () use ($user, $type, $file, $path, $existing, $profile, $expiresAt, $metadata) {
            $doc = ProviderOnboardingDocument::create([
                'user_id' => $user->id,
                'document_type' => $type,
                'status' => ProviderOnboardingDocument::STATUS_PENDING,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'expires_at' => $expiresAt,
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);

            // Si on remplace, on archive l'ancien
            if ($existing && $existing->id !== $doc->id) {
                $existing->update([
                    'status' => ProviderOnboardingDocument::STATUS_REJECTED,
                    'rejection_reason' => 'Remplacé par une nouvelle version',
                ]);
            }

            // Avance la step selon le type uploadé
            $stepForType = match ($type) {
                ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
                ProviderOnboardingDocument::TYPE_PASSPORT,
                ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT => self::STEP_IDENTITY,
                ProviderOnboardingDocument::TYPE_INSURANCE => self::STEP_INSURANCE,
                default => null,
            };
            if ($stepForType !== null) {
                $this->advanceStepIfNeeded($profile, $stepForType);
            }

            return $doc;
        });

        // Cette piece etait peut-etre la derniere manquante : le compte doit alors s'ouvrir sans
        // attendre. Soft-fail — un depot reussi ne doit pas echouer parce que l'orchestration
        // d'activation a echoue ; le dossier reste alors en attente d'un administrateur.
        try {
            app(ProviderAutoApproval::class)->evaluate($user);
        } catch (\Throwable $e) {
            Log::warning('[provider_auto_approval] reevaluation impossible apres depot', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $document;
    }

    /** Étape 2 — Numéro TVA / fiscal. */
    public function setTaxInfo(User $user, ?string $taxId): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        $metadata = $profile->metadata ?? [];
        $metadata['tax_id'] = $taxId;
        $profile->update(['metadata' => $metadata]);

        if (! empty($taxId)) {
            $this->advanceStepIfNeeded($profile, self::STEP_TAX);
        }

        return $profile->fresh();
    }

    /** Étape 4 — Métiers et zones d'intervention déclarés. */
    /**
     * Ce que le prestataire declare faire — porte jusqu'a la table que la repartition lit.
     *
     * ON PASSE PAR `ProviderCoverageWriter`, qui se declare « ecrit une seule fois » : il valide
     * les metiers actifs, tient `trade_user.is_primary`, desactive les zones retirees plutot que
     * de les supprimer, et pose `primary_service_zone_id` — que cette etape ne posait PAS, ce qui
     * laissait le prestataire invisible aux rendez-vous apres avoir declare sa couverture.
     *
     * @param  list<string>  $skills
     * @param  list<int>  $zoneIds
     */
    private function couvrirLesMetiersDeclares(User $user, array $skills, array $zoneIds): void
    {
        $declares = $this->metiersDepuisIdentifiants($skills);

        // L'UNION, jamais le remplacement : voir le commentaire de l'appelant.
        $deja = $user->trades()->pluck('trades.id')->map(fn ($id) => (int) $id)->all();

        app(ProviderCoverageWriter::class)->sync(
            $user,
            array_values(array_unique([...$deja, ...$declares])),
            $zoneIds,
        );
    }

    /**
     * Les trois ecritures acceptees : identifiant, code catalogue, ou slug.
     *
     * Le natif envoie des slugs, l'administration des identifiants. Ce qui ne se resout pas est
     * ignore ICI mais reste dans `skills` : on ne perd pas ce que le prestataire a declare.
     *
     * @param  list<string>  $skills
     * @return list<int>
     */
    private function metiersDepuisIdentifiants(array $skills): array
    {
        $valeurs = array_values(array_filter(array_map('strval', $skills), fn ($v) => $v !== ''));

        if ($valeurs === []) {
            return [];
        }

        return Trade::query()
            ->whereIn('id', array_values(array_filter($valeurs, 'is_numeric')))
            ->orWhereIn('code', $valeurs)
            ->orWhereIn('slug', $valeurs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function setSkills(User $user, array $skills, array $serviceZoneIds = []): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        $zoneIds = array_values(array_unique(array_map('intval', $serviceZoneIds)));

        $metadata = $profile->metadata ?? [];
        $metadata['service_zone_ids'] = $zoneIds;

        $profile->update([
            'skills' => array_values(array_unique($skills)),
            'metadata' => $metadata,
        ]);

        /*
         * ET LA COUVERTURE QUI COMPTE VRAIMENT.
         *
         * `provider_profiles.skills` n'est lu QUE par cet assistant et par le compteur
         * d'avancement : la repartition, elle, joint `trade_user` (CandidateFinder). Declarer ses
         * metiers ici ne changeait donc rien a ce qu'on pouvait recevoir.
         *
         * ON AJOUTE, ON NE REMPLACE PAS : aucun des deux ecrans ne pre-coche la couverture reelle
         * — celui du natif dit lui-meme servir « a confirmer, et a en ajouter ». Un `sync`
         * detachant effacerait les metiers declares a l'inscription. Le retrait reste ou il est
         * deja : l'inscription, l'admin, le profil.
         */
        $this->couvrirLesMetiersDeclares($user, $skills, $zoneIds);

        if (! empty($skills)) {
            $this->advanceStepIfNeeded($profile, self::STEP_SKILLS);
        }

        return $profile->fresh();
    }

    /** Étape 5 — Stripe Connect onboarding. */
    public function markStripeConnectComplete(User $user): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        if ($user->stripe_connect_status === 'active') {
            $this->advanceStepIfNeeded($profile, self::STEP_STRIPE_CONNECT);
        }

        return $profile->fresh();
    }

    /** Validation finale par l'admin. */
    public function approveOnboarding(User $user, User $admin): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        // Vérification : au moins 1 document d'identité approved
        $hasIdentity = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->approved()
            ->whereIn('document_type', [
                ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
                ProviderOnboardingDocument::TYPE_PASSPORT,
                ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT,
            ])
            ->exists();

        if (! $hasIdentity) {
            throw new \DomainException('Aucun document d\'identité approuvé.');
        }

        // Vérification : insurance approved
        $hasInsurance = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->approved()
            ->where('document_type', ProviderOnboardingDocument::TYPE_INSURANCE)
            ->exists();

        if (! $hasInsurance) {
            throw new \DomainException('Document d\'assurance non approuvé.');
        }

        // Stripe Connect actif
        if ($user->stripe_connect_status !== 'active') {
            throw new \DomainException('Compte Stripe Connect non actif.');
        }

        $profile->update([
            'verification_status' => 'verified',
            'status' => 'active',
            'onboarding_step' => self::STEP_READY,
            'onboarding_completed_at' => now(),
            'metadata' => array_merge($profile->metadata ?? [], [
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now()->toIso8601String(),
            ]),
        ]);

        // M19 — write-through to OnboardingV2: the legacy wizard is the real onboarding path, but
        // its progress wasn't reflected in the v2 journey (admin OnboardingV2Center showed
        // providers as never-started). Admin approval is the authoritative completion signal, so
        // mirror the v2 journey to complete. Soft-fail: never block the legacy approval.
        $this->syncOnboardingV2Completed($user);

        return $profile->fresh();
    }

    /** Point d'entrée public du pont vers Onboarding v2. */
    public function markOnboardingV2Completed(User $user): void
    {
        $this->syncOnboardingV2Completed($user);
    }

    /** Mark the user's OnboardingV2 journey complete to mirror the legacy approval. */
    protected function syncOnboardingV2Completed(User $user): void
    {
        if (! config('onboarding_v2.enabled', true)) {
            return;
        }

        try {
            $progress = app(OnboardingEngine::class)->startFor($user);

            $progress->completions()->update([
                'status' => OnboardingStepCompletion::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $progress->forceFill([
                'status' => OnboardingProgress::STATUS_COMPLETED,
                'percent_complete' => 100,
                'completed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('[onboarding_v2_sync] failed (non-blocking)', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Réviser un document (admin) — approuver ou rejeter. */
    public function reviewDocument(
        ProviderOnboardingDocument $document,
        User $admin,
        bool $approve,
        ?string $rejectionReason = null,
    ): ProviderOnboardingDocument {
        $document->update([
            'status' => $approve
                ? ProviderOnboardingDocument::STATUS_APPROVED
                : ProviderOnboardingDocument::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $approve ? null : $rejectionReason,
        ]);

        return $document->fresh();
    }

    /** Renvoie l'état d'avancement complet pour la UI. */
    public function getProgress(User $user): array
    {
        $profile = ProviderProfile::where('user_id', $user->id)->first();
        if (! $profile) {
            return [
                'started' => false,
                'current_step' => 0,
                'total_steps' => 7,
                'completed' => false,
                'documents' => [],
            ];
        }

        $documents = ProviderOnboardingDocument::forUser($user->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('document_type')
            ->map(fn ($docs) => [
                'latest_status' => $docs->first()->status,
                'count' => $docs->count(),
                'rejection_reason' => $docs->first()->rejection_reason,
            ])
            ->all();

        return [
            'started' => true,
            'current_step' => (int) $profile->onboarding_step,
            'total_steps' => 7,
            'completed' => $profile->onboarding_completed_at !== null,
            'completed_at' => $profile->onboarding_completed_at?->toIso8601String(),
            'verification_status' => $profile->verification_status,
            'documents' => $documents,
            'has_bio' => filled($profile->bio),
            'has_photo' => filled($profile->photo_path),
            'has_tax_id' => filled($profile->metadata['tax_id'] ?? null),
            'has_skills' => is_array($profile->skills) && count($profile->skills) > 0,
            'stripe_active' => $user->stripe_connect_status === 'active',
        ];
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function ensureProfile(User $user): ProviderProfile
    {
        return $this->startOnboarding($user);
    }

    protected function advanceStepIfNeeded(ProviderProfile $profile, int $newStep): void
    {
        if ((int) $profile->onboarding_step < $newStep) {
            $profile->update(['onboarding_step' => $newStep]);
        }
    }

    protected function validateDocumentType(string $type): void
    {
        $valid = [
            ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            ProviderOnboardingDocument::TYPE_PASSPORT,
            ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT,
            ProviderOnboardingDocument::TYPE_TAX_ID,
            ProviderOnboardingDocument::TYPE_INSURANCE,
            ProviderOnboardingDocument::TYPE_DIPLOMA,
            ProviderOnboardingDocument::TYPE_CRIMINAL_RECORD,
            // Les pièces de la conduite : sans elles dans cette liste, l'upload lèverait
            // « Type de document invalide » pour une exigence que le dossier réclame par ailleurs.
            ProviderOnboardingDocument::TYPE_DRIVING_LICENSE,
            ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION,
            ProviderOnboardingDocument::TYPE_VEHICLE_INSURANCE,
            ProviderOnboardingDocument::TYPE_OTHER,
        ];

        if (! in_array($type, $valid, true)) {
            throw new \InvalidArgumentException("Type de document invalide : {$type}");
        }
    }
}
