<?php

namespace App\Services\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 14 — Service de gestion de l'onboarding prestataire.
 *
 * Étapes (onboarding_step sur ProviderProfile) :
 *   0. profile_basics  (nom, photo, bio)
 *   1. identity        (1 doc parmi: identity_card | passport | residence_permit)
 *   2. tax             (numéro TVA / SIREN)
 *   3. insurance       (attestation responsabilité civile pro)
 *   4. skills          (sélection métiers + zones)
 *   5. stripe_connect  (lien onboarding Stripe via StripeConnectService existant)
 *   6. ready           (admin valide → verification_status = 'verified')
 *
 * Toutes les méthodes sont idempotentes : on peut re-uploader un document,
 * re-définir des skills, etc. tant que onboarding_completed_at est null.
 */
class ProviderOnboardingService
{
    public const STEP_PROFILE_BASICS  = 0;
    public const STEP_IDENTITY        = 1;
    public const STEP_TAX             = 2;
    public const STEP_INSURANCE       = 3;
    public const STEP_SKILLS          = 4;
    public const STEP_STRIPE_CONNECT  = 5;
    public const STEP_READY           = 6;

    /**
     * Crée un ProviderProfile vide pour un user qui s'inscrit comme prestataire.
     * Idempotent : si un profil existe déjà, on le retourne tel quel.
     */
    public function startOnboarding(User $user): ProviderProfile
    {
        return ProviderProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'provider_type'       => 'individual',
                'status'              => 'pending',
                'verification_status' => 'pending',
                'onboarding_step'     => self::STEP_PROFILE_BASICS,
                'commission_rate'     => 20.00,
            ]
        );
    }

    /**
     * Étape 0 — Infos basiques (nom, photo, bio).
     */
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

    /**
     * Étape 1, 3, 6, 7 — Upload d'un document.
     */
    public function uploadDocument(User $user, string $type, UploadedFile $file): ProviderOnboardingDocument
    {
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

        return DB::transaction(function () use ($user, $type, $file, $path, $existing, $profile) {
            $doc = ProviderOnboardingDocument::create([
                'user_id'       => $user->id,
                'document_type' => $type,
                'status'        => ProviderOnboardingDocument::STATUS_PENDING,
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'file_size'     => $file->getSize(),
            ]);

            // Si on remplace, on archive l'ancien
            if ($existing && $existing->id !== $doc->id) {
                $existing->update([
                    'status'   => ProviderOnboardingDocument::STATUS_REJECTED,
                    'rejection_reason' => 'Remplacé par une nouvelle version',
                ]);
            }

            // Avance la step selon le type uploadé
            $stepForType = match ($type) {
                ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
                ProviderOnboardingDocument::TYPE_PASSPORT,
                ProviderOnboardingDocument::TYPE_RESIDENCE_PERMIT => self::STEP_IDENTITY,
                ProviderOnboardingDocument::TYPE_INSURANCE        => self::STEP_INSURANCE,
                default                                            => null,
            };
            if ($stepForType !== null) {
                $this->advanceStepIfNeeded($profile, $stepForType);
            }

            return $doc;
        });
    }

    /**
     * Étape 2 — Numéro TVA / fiscal.
     */
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

    /**
     * Étape 4 — Compétences/métiers + zones de travail.
     */
    public function setSkills(User $user, array $skills, array $serviceZoneIds = []): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        $metadata = $profile->metadata ?? [];
        $metadata['service_zone_ids'] = array_values(array_unique(array_map('intval', $serviceZoneIds)));

        $profile->update([
            'skills'   => array_values(array_unique($skills)),
            'metadata' => $metadata,
        ]);

        if (! empty($skills)) {
            $this->advanceStepIfNeeded($profile, self::STEP_SKILLS);
        }

        return $profile->fresh();
    }

    /**
     * Étape 5 — Stripe Connect onboarding.
     * Marque l'étape comme passée si stripe_connect_status='active'.
     */
    public function markStripeConnectComplete(User $user): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        if ($user->stripe_connect_status === 'active') {
            $this->advanceStepIfNeeded($profile, self::STEP_STRIPE_CONNECT);
        }

        return $profile->fresh();
    }

    /**
     * Validation finale par l'admin.
     * Vérifie que tous les documents requis sont approved + stripe connect actif.
     */
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
            'verification_status'      => 'verified',
            'status'                   => 'active',
            'onboarding_step'          => self::STEP_READY,
            'onboarding_completed_at'  => now(),
            'metadata'                 => array_merge($profile->metadata ?? [], [
                'approved_by_admin_id' => $admin->id,
                'approved_at'          => now()->toIso8601String(),
            ]),
        ]);

        return $profile->fresh();
    }

    /**
     * Réviser un document (admin) — approuver ou rejeter.
     */
    public function reviewDocument(
        ProviderOnboardingDocument $document,
        User $admin,
        bool $approve,
        ?string $rejectionReason = null,
    ): ProviderOnboardingDocument {
        $document->update([
            'status'           => $approve
                ? ProviderOnboardingDocument::STATUS_APPROVED
                : ProviderOnboardingDocument::STATUS_REJECTED,
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $approve ? null : $rejectionReason,
        ]);

        return $document->fresh();
    }

    /**
     * Renvoie l'état d'avancement complet pour la UI.
     */
    public function getProgress(User $user): array
    {
        $profile = ProviderProfile::where('user_id', $user->id)->first();
        if (! $profile) {
            return [
                'started'          => false,
                'current_step'     => 0,
                'total_steps'      => 7,
                'completed'        => false,
                'documents'        => [],
            ];
        }

        $documents = ProviderOnboardingDocument::forUser($user->id)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('document_type')
            ->map(fn ($docs) => [
                'latest_status'  => $docs->first()->status,
                'count'          => $docs->count(),
                'rejection_reason' => $docs->first()->rejection_reason,
            ])
            ->all();

        return [
            'started'          => true,
            'current_step'     => (int) $profile->onboarding_step,
            'total_steps'      => 7,
            'completed'        => $profile->onboarding_completed_at !== null,
            'completed_at'     => $profile->onboarding_completed_at?->toIso8601String(),
            'verification_status' => $profile->verification_status,
            'documents'        => $documents,
            'has_bio'          => filled($profile->bio),
            'has_photo'        => filled($profile->photo_path),
            'has_tax_id'       => filled($profile->metadata['tax_id'] ?? null),
            'has_skills'       => is_array($profile->skills) && count($profile->skills) > 0,
            'stripe_active'    => $user->stripe_connect_status === 'active',
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
            ProviderOnboardingDocument::TYPE_OTHER,
        ];

        if (! in_array($type, $valid, true)) {
            throw new \InvalidArgumentException("Type de document invalide : {$type}");
        }
    }
}
