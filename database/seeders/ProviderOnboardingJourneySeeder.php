<?php

namespace Database\Seeders;

use App\Models\OnboardingJourney;
use App\Models\OnboardingStep;
use App\Models\ProviderOnboardingDocument;
use App\Services\OnboardingV2\Validators\ContractSignValidator;
use App\Services\OnboardingV2\Validators\DocumentUploadValidator;
use App\Services\OnboardingV2\Validators\KycCheckValidator;
use App\Services\OnboardingV2\Validators\ProfileCompleteValidator;
use App\Services\OnboardingV2\Validators\SkillDeclareValidator;
use Illuminate\Database\Seeder;

/**
 * Parcours de vérification obligatoire d'un prestataire avant l'accès au tableau de bord.
 *
 * Le module Onboarding v2 était entièrement construit — moteur, huit validateurs, API — mais
 * AUCUN parcours n'était configuré : `onboarding_journeys` et `onboarding_steps` étaient vides,
 * si bien qu'aucune vérification ne s'appliquait jamais. Ce seeder définit celui des prestataires.
 *
 * Le code `provider_default` n'est pas arbitraire : config/onboarding_v2.php mappe déjà les rôles
 * `provider` et `employe` vers lui, donc OnboardingEngine::startFor() le résout sans argument.
 *
 * Périmètre décidé : profil, contrat, identité, documents, compétences sont BLOQUANTS. Stripe
 * Connect et l'assurance ne le sont pas — un prestataire peut être vérifié sans être encore
 * configuré pour être payé, et bloquer l'inscription là-dessus l'allongerait beaucoup. Leurs
 * validateurs existent et pourront être ajoutés ici sans toucher au code.
 *
 * Idempotent : rejouable sans dupliquer, les étapes sont réconciliées sur (journey, code).
 */
class ProviderOnboardingJourneySeeder extends Seeder
{
    public const JOURNEY_CODE = 'provider_default';

    /**
     * Version du contrat prestataire acceptée à l'étape `contract_sign`.
     *
     * ContractSignValidator privilégie une signature Contracts v2 via `template_code`, mais
     * `contract_templates` est vide : ce chemin échouerait donc systématiquement. On emploie son
     * repli par version, autonome. À basculer sur `template_code` le jour où des modèles sont
     * publiés — sans quoi cette étape resterait infranchissable.
     */
    public const CONTRACT_VERSION = '1.0';

    public function run(): void
    {
        $journey = OnboardingJourney::updateOrCreate(
            ['code' => self::JOURNEY_CODE],
            [
                'name' => 'Vérification prestataire',
                'description' => "Étapes à compléter avant d'accéder à l'application et de recevoir des missions.",
                'role' => 'provider',
                'is_active' => true,
                'version' => 1,
            ],
        );

        foreach ($this->steps() as $position => $step) {
            OnboardingStep::updateOrCreate(
                ['journey_id' => $journey->id, 'code' => $step['code']],
                array_merge($step, ['journey_id' => $journey->id, 'position' => $position + 1]),
            );
        }
    }

    /**
     * L'ordre porte du sens : on identifie la personne avant de lui demander ses pièces, et on
     * ne déclare ses métiers qu'une fois son identité établie. `depends_on` rend cet
     * enchaînement explicite pour le moteur plutôt que de le laisser à la seule position.
     *
     * @return array<int, array<string, mixed>>
     */
    private function steps(): array
    {
        return [
            [
                'code' => 'profile_complete',
                'label' => 'Compléter votre profil',
                'description' => 'Nom, téléphone et informations de contact.',
                'step_type' => 'profile',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => ProfileCompleteValidator::class,
                'depends_on' => [],
                // `phone` en plus des défauts du validateur : c'est le seul de ces champs que
                // l'inscription ne remplit pas, donc le seul que cette étape fasse réellement
                // compléter. Sans lui, l'étape passerait sans rien demander.
                'metadata' => ['required_user_fields' => ['name', 'email', 'phone']],
            ],
            [
                'code' => 'contract_sign',
                'label' => 'Signer le contrat prestataire',
                'description' => "Conditions d'intervention et engagement de service.",
                'step_type' => 'contract',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => ContractSignValidator::class,
                'depends_on' => ['profile_complete'],
                'metadata' => ['required_version' => self::CONTRACT_VERSION],
            ],
            [
                'code' => 'kyc_check',
                'label' => 'Vérifier votre identité',
                'description' => "Pièce d'identité, contrôlée automatiquement.",
                'step_type' => 'kyc',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => KycCheckValidator::class,
                'depends_on' => ['profile_complete'],
                'metadata' => [],
            ],
            [
                'code' => 'document_upload',
                'label' => 'Déposer vos justificatifs',
                'description' => 'Attestations, certifications et pièces demandées pour vos métiers.',
                'step_type' => 'documents',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => DocumentUploadValidator::class,
                'depends_on' => ['kyc_check'],
                // Sans `document_types`, le validateur passe SANS rien exiger. La pièce
                // d'identité est le seul justificatif universel ; l'assurance et les diplômes
                // dépendent du métier et se demandent à la revue admin.
                'metadata' => ['document_types' => [ProviderOnboardingDocument::TYPE_IDENTITY_CARD]],
            ],
            [
                'code' => 'skill_declare',
                'label' => 'Déclarer vos métiers',
                'description' => 'Sans métier déclaré, aucune mission ne peut vous être proposée.',
                'step_type' => 'skills',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => SkillDeclareValidator::class,
                'depends_on' => ['profile_complete'],
                'metadata' => ['min_skills_count' => 1],
            ],
        ];
    }
}
