<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\Onboarding\ProviderVehicleService;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/**
 * LE VÉHICULE DÉCLARÉ, ET SON ÂGE — pour les seuls métiers sous règles taxi.
 *
 * IL PASSE TRIVIALEMENT QUAND PERSONNE N'EST CONCERNÉ, et c'est le point le plus important de cette
 * classe. Le parcours d'inscription est UNIQUE : la même suite d'étapes sert le peintre, la garde
 * d'enfants et le chauffeur. Une étape « déclarez votre véhicule » qui bloquerait un jardinier
 * serait pire que le trou qu'elle vient combler — elle empêcherait des inscriptions légitimes,
 * silencieusement, sur un métier qui n'a jamais eu de voiture.
 *
 * Le REFUS, lui, dit toujours quoi faire : « aucun véhicule déclaré », « date de première
 * immatriculation manquante », « six ans, la limite est de quatre ». Un refus qui ne dit pas
 * lequel des trois est en cause fait recommencer au hasard, puis appeler le support.
 */
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

        /*
         * La carte grise est ce qui rend la date OPPOSABLE.
         *
         * L'âge est calculé depuis une date que le prestataire a saisie lui-même : sans la pièce
         * qui l'atteste, le contrôle mesurerait une déclaration, pas un fait. C'est exactement la
         * raison pour laquelle Uber et Bolt réclament le certificat d'immatriculation plutôt que de
         * se contenter d'un formulaire.
         */
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
