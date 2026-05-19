<?php

namespace Database\Seeders;

use App\Models\OnboardingJourney;
use App\Models\OnboardingStep;
use Illuminate\Database\Seeder;

class OnboardingJourneysSeeder extends Seeder
{
    public function run(): void
    {
        $journeys = [
            [
                'journey' => [
                    'code' => 'client_default',
                    'name' => 'Onboarding client par défaut',
                    'description' => 'Parcours minimal pour un nouveau client',
                    'role' => 'client',
                    'is_active' => true,
                    'version' => 1,
                ],
                'steps' => [
                    [
                        'code' => 'profile', 'label' => 'Compléter le profil', 'step_type' => 'profile_complete',
                        'required' => true, 'is_skippable' => false,
                        'metadata' => ['required_user_fields' => ['name', 'email', 'locale']],
                    ],
                    [
                        'code' => 'phone_verify', 'label' => 'Vérifier le téléphone', 'step_type' => 'form',
                        'required' => false, 'is_skippable' => true,
                        'depends_on' => ['profile'],
                        'metadata' => ['required_fields' => ['phone_verified_at']],
                    ],
                    [
                        'code' => 'tos', 'label' => 'Accepter les conditions générales', 'step_type' => 'contract_sign',
                        'required' => true, 'is_skippable' => false,
                        'metadata' => ['required_version' => '2026-05-v1'],
                    ],
                ],
            ],
            [
                'journey' => [
                    'code' => 'provider_default',
                    'name' => 'Onboarding provider par défaut',
                    'description' => 'Parcours complet pour un nouveau provider (KYC + payouts + insurance)',
                    'role' => 'provider',
                    'is_active' => true,
                    'version' => 1,
                ],
                'steps' => [
                    [
                        'code' => 'profile', 'label' => 'Compléter le profil', 'step_type' => 'profile_complete',
                        'required' => true, 'is_skippable' => false,
                        'metadata' => ['required_user_fields' => ['name', 'email', 'phone', 'locale']],
                    ],
                    [
                        'code' => 'tos', 'label' => 'Accepter contrat provider', 'step_type' => 'contract_sign',
                        'required' => true, 'is_skippable' => false,
                        'depends_on' => ['profile'],
                        'metadata' => ['required_version' => '2026-05-provider-v1'],
                    ],
                    [
                        'code' => 'kyc', 'label' => 'Vérification d\'identité (KYC)', 'step_type' => 'kyc_check',
                        'required' => true, 'is_skippable' => false,
                        'depends_on' => ['tos'],
                    ],
                    [
                        'code' => 'skills', 'label' => 'Déclarer ses métiers', 'step_type' => 'skill_declare',
                        'required' => true, 'is_skippable' => false,
                        'depends_on' => ['kyc'],
                        'metadata' => ['min_skills_count' => 1],
                    ],
                    [
                        'code' => 'documents', 'label' => 'Uploader documents officiels', 'step_type' => 'document_upload',
                        'required' => true, 'is_skippable' => false,
                        'depends_on' => ['kyc'],
                        'metadata' => ['document_types' => ['id_card', 'insurance_proof']],
                    ],
                    [
                        'code' => 'payouts', 'label' => 'Lier le compte Stripe Connect', 'step_type' => 'payouts_setup',
                        'required' => true, 'is_skippable' => false,
                        'depends_on' => ['kyc'],
                    ],
                    [
                        'code' => 'insurance', 'label' => 'Couverture assurance', 'step_type' => 'insurance_purchase',
                        'required' => false, 'is_skippable' => true,
                        'depends_on' => ['payouts'],
                    ],
                ],
            ],
        ];

        foreach ($journeys as $template) {
            $journey = OnboardingJourney::query()->updateOrCreate(
                ['code' => $template['journey']['code']],
                $template['journey'],
            );
            $journey->steps()->delete();
            foreach (array_values($template['steps']) as $i => $step) {
                OnboardingStep::create(array_merge([
                    'journey_id' => $journey->id,
                    'position' => $i + 1,
                    'required' => $step['required'] ?? true,
                    'is_skippable' => $step['is_skippable'] ?? false,
                ], $step));
            }
        }
    }
}
