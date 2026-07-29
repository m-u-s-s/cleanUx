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
            // `provider_default` n'est PAS défini ici : voir la délégation en fin de méthode.
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

        // Le parcours prestataire appartient à ProviderOnboardingJourneySeeder, propriétaire
        // unique : ses codes d'étapes sont exactement ceux que l'app mobile sait rendre
        // (STEP_COMPONENTS de ProviderOnboardingScreen). Il était AUSSI défini ici, avec sept
        // codes différents et le steps()->delete() ci-dessus — et comme ProductionBootstrapSeeder
        // appelle ce seeder APRÈS le référentiel, c'est cette version-là qui gagnait en
        // production : chaque étape se serait affichée « non disponible » dans l'app.
        //
        // On délègue plutôt que de simplement retirer le bloc, pour que « seeder les parcours »
        // continue de tous les produire, quel que soit le profil de seed appelé. Le seeder
        // délégué est idempotent, le double appel de ProductionBootstrapSeeder est donc sans effet.
        $this->call(ProviderOnboardingJourneySeeder::class);
    }
}
