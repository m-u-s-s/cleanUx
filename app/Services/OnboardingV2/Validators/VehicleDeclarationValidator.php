<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\Onboarding\ProviderVehicleService;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/** LE VÉHICULE DÉCLARÉ, ET SON ÂGE — pour les seuls métiers sous règles taxi. */
class VehicleDeclarationValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $vehicules = app(ProviderVehicleService::class);
        $dossier = $vehicules->dossier($user);

        if (! $dossier['requis']) {
            return OnboardingStepValidation::pass(metadata: ['vehicle_required' => false]);
        }

        if (! $dossier['conforme']) {
            return OnboardingStepValidation::fail(['vehicle' => (string) $dossier['motif']]);
        }

        // La carte grise est ce qui rend la date OPPOSABLE.
        if (! $vehicules->carteGriseDeposee($user)) {
            return OnboardingStepValidation::fail([
                'vehicle' => 'Déposez le certificat d’immatriculation : c’est lui qui atteste la date de première mise en circulation.',
            ]);
        }

        return OnboardingStepValidation::pass(metadata: [
            'vehicle_required' => true,
            'age_years' => $dossier['age'],
            'max_age_years' => $dossier['limite'],
        ]);
    }
}
