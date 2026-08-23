<?php

namespace App\Services\Onboarding;

use App\Models\BusinessEntity;
use App\Models\KycVerification;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Activation automatique d'un compte prestataire. */
class ProviderAutoApproval
{
    /** Le dossier est complet : le compte vient d'être ouvert. */
    public const OUTCOME_APPROVED = 'approved';

    /** Un contrôle automatique a rendu un verdict négatif : un humain doit regarder. */
    public const OUTCOME_MANUAL_REVIEW = 'manual_review';

    /** Il manque encore des éléments au prestataire lui-même. */
    public const OUTCOME_INCOMPLETE = 'incomplete';

    /** Le compte n'est pas concerné (prestataire historique, déjà actif, déjà refusé). */
    public const OUTCOME_SKIPPED = 'skipped';

    public function __construct(
        protected ProviderDossierSummary $summary,
        protected ProviderOnboardingService $onboarding,
    ) {}

    /** Réévalue le dossier et ouvre le compte s'il est complet. */
    public function evaluate(User $user): string
    {
        $profile = $user->providerProfile;

        if (! $this->isEligible($profile)) {
            return self::OUTCOME_SKIPPED;
        }

        $dossier = $this->summary->for($user);

        // Un refus d'identité est un signal fort, mais c'est un humain qui doit trancher : le
        // prestataire a pu photographier une pièce de travers, ce qui n'est pas une fraude.
        if ($dossier['identity']['decision'] === KycVerification::DECISION_REJECTED) {
            $this->flagForManualReview($profile, "Vérification d'identité refusée par le contrôle automatique");

            return self::OUTCOME_MANUAL_REVIEW;
        }

        // Vérification d'entreprise : un risque élevé ou un refus n'annule pas l'inscription, il
        // la fait examiner. Une société non vérifiée ne doit pas non plus s'ouvrir toute seule.
        if ($business = $this->businessVerdict($user)) {
            $this->flagForManualReview($profile, $business);

            return self::OUTCOME_MANUAL_REVIEW;
        }

        if (! $dossier['is_complete']) {
            return self::OUTCOME_INCOMPLETE;
        }

        $this->approve($profile, $user, $dossier);

        return self::OUTCOME_APPROVED;
    }

    /** Motif d'examen humain lié à l'entreprise, s'il y en a un. */
    private function businessVerdict(User $user): ?string
    {
        $entity = BusinessEntity::query()
            ->where('owner_user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $entity) {
            return null;
        }

        return match ($entity->status) {
            BusinessEntity::STATUS_NEEDS_REVIEW => "Vérification d'entreprise à examiner (risque élevé ou contrôle incomplet)",
            BusinessEntity::STATUS_REJECTED => "Vérification d'entreprise refusée par le contrôle automatique",
            BusinessEntity::STATUS_SUSPENDED => 'Entreprise suspendue',
            default => null,
        };
    }

    /** Oriente explicitement un dossier vers l'examen humain, sans le refuser. */
    public function flagForManualReview(ProviderProfile $profile, string $reason): void
    {
        $profile->forceFill([
            'metadata' => array_merge($profile->metadata ?? [], [
                'auto_review_outcome' => self::OUTCOME_MANUAL_REVIEW,
                'auto_review_reason' => $reason,
                'auto_review_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    /** Seuls les comptes créés par l'inscription en libre-service et encore en attente sont concernés. */
    private function isEligible(?ProviderProfile $profile): bool
    {
        return $profile !== null
            && $profile->self_registered_at !== null
            && $profile->status === 'pending';
    }

    /** @param array<string, mixed> $dossier */
    private function approve(ProviderProfile $profile, User $user, array $dossier): void
    {
        DB::transaction(function () use ($profile, $user, $dossier) {
            $attributes = ['status' => 'active'];

            // `verified` affirme une vérification humaine des pièces : on ne l'écrit que si elle
            // a eu lieu. Un compte peut donc être actif sans être certifié, et c'est exact.
            if ($dossier['can_mark_verified']) {
                $attributes['verification_status'] = 'verified';
                $attributes['verified_at'] = $profile->verified_at ?? now();
            }

            $attributes['metadata'] = array_merge($profile->metadata ?? [], [
                'auto_review_outcome' => self::OUTCOME_APPROVED,
                'auto_approved_at' => now()->toIso8601String(),
                'auto_approved_documents_pending' => $dossier['documents']['pending'],
                // Une évaluation antérieure a pu orienter vers l'examen humain ; le dossier
                // ayant abouti, ce motif n'a plus lieu d'être affiché.
                'auto_review_reason' => null,
            ]);

            $profile->forceFill($attributes)->save();

            if ($profile->organization_account_id) {
                OrganizationAccount::query()
                    ->whereKey($profile->organization_account_id)
                    ->update(['status' => 'active']);
            }

            $this->onboarding->markOnboardingV2Completed($user);
        });

        Log::info('[provider_auto_approval] compte activé automatiquement', [
            'user_id' => $user->id,
            'documents_pending' => $dossier['documents']['pending'],
        ]);
    }
}
