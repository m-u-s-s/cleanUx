<?php

namespace App\Services\Onboarding;

use App\Models\BusinessEntity;
use App\Models\KycVerification;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Activation automatique d'un compte prestataire.
 *
 * Les contrôles automatiques existaient déjà et rendaient un verdict — l'identité s'auto-approuve
 * au-dessus du score minimal, la vérification d'entreprise sait scorer un risque — mais PERSONNE
 * ne faisait jamais passer `provider_profiles.status` à `active`. Or c'est cette colonne que garde
 * le middleware : un dossier entièrement vert restait bloqué jusqu'au clic d'un administrateur.
 *
 * Règle retenue : le compte s'ouvre dès que l'identité est validée et que les pièces exigées sont
 * DÉPOSÉES, sans attendre leur relecture. C'est ce qui permet d'activer en minutes plutôt qu'en
 * jours. La relecture devient un contrôle a posteriori, et `verification_status` ne devient
 * `verified` que lorsqu'elle a réellement eu lieu — être actif n'est pas être certifié.
 *
 * Ce que ce service ne fait JAMAIS :
 *
 *  - refuser un compte. Un contrôle automatique négatif oriente vers un examen humain, il ne
 *    tranche pas : un faux positif de criblage ne doit pas fermer une inscription légitime.
 *  - toucher un prestataire d'avant l'inscription en libre-service, ni un compte déjà traité.
 */
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

    /**
     * Réévalue le dossier et ouvre le compte s'il est complet.
     *
     * Appelée après chaque événement susceptible de changer le verdict : décision d'identité,
     * dépôt de pièce, étape franchie. Idempotente — un compte déjà actif est simplement ignoré.
     */
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

    /**
     * Motif d'examen humain lié à l'entreprise, s'il y en a un.
     *
     * Ne concerne que les sociétés : un indépendant n'a pas d'entité à vérifier. Une entité
     * encore en cours de contrôle ne bloque pas — le compte s'ouvrira à la réévaluation qui
     * suivra son verdict.
     */
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

    /**
     * Oriente explicitement un dossier vers l'examen humain, sans le refuser.
     *
     * Employée aussi depuis l'écoute du refus d'identité, pour que la trace existe même si
     * l'évaluation complète n'est pas rejouée.
     */
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

    /**
     * Seuls les comptes créés par l'inscription en libre-service et encore en attente sont
     * concernés. Les prestataires antérieurs n'ont jamais été soumis à cette attente, et un
     * compte déjà actif ou refusé a été tranché.
     */
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
