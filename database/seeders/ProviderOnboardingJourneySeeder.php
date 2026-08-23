<?php

namespace Database\Seeders;

use App\Models\OnboardingJourney;
use App\Models\OnboardingStep;
use App\Services\OnboardingV2\Validators\ContractSignValidator;
use App\Services\OnboardingV2\Validators\DocumentUploadValidator;
use App\Services\OnboardingV2\Validators\KycCheckValidator;
use App\Services\OnboardingV2\Validators\ProfileCompleteValidator;
use App\Services\OnboardingV2\Validators\SkillDeclareValidator;
use App\Services\OnboardingV2\Validators\VehicleDeclarationValidator;
use Illuminate\Database\Seeder;

/** Parcours de vérification obligatoire d'un prestataire avant l'accès au tableau de bord. */
class ProviderOnboardingJourneySeeder extends Seeder
{
    public const JOURNEY_CODE = 'provider_default';

    /** Version du contrat prestataire acceptée à l'étape `contract_sign`. */
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
     * L'ordre porte du sens : on identifie la personne avant de lui demander ses pièces, et on ne déclare ses métiers qu'une fois son identité établie.
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
                // Les deux, et pas l'un OU l'autre : `template_code` engage la vraie signature
                // Contracts v2 quand le modèle est seedé, `required_version` reste le repli
                // autonome d'un déploiement où il ne l'est pas. Le validateur bascule seul.
                'metadata' => [
                    'template_code' => 'provider_agreement',
                    'required_version' => self::CONTRACT_VERSION,
                ],
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
                // Pas de `document_types` ici : la liste se dérive des métiers déclarés
                // (ProviderDocumentRequirements). Elle était figée sur la seule pièce d'identité,
                // si bien qu'un électricien n'était jamais invité à déposer sa certification ni un
                // peintre son assurance — que la validation finale du dossier exige pourtant.
                'metadata' => [],
            ],
            [
                // LE VÉHICULE — pour les seuls métiers sous règles taxi.
                'code' => 'vehicle_declare',
                'label' => 'Déclarer votre véhicule',
                'description' => 'Marque, modèle, plaque et date de première immatriculation — exigé pour les services de transport de personnes.',
                'step_type' => 'vehicle_declare',
                'required' => true,
                'is_skippable' => false,
                'validator_class' => VehicleDeclarationValidator::class,
                'depends_on' => ['document_upload'],
                'metadata' => [],
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
