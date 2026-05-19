<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/**
 * Validator générique pour les steps `form`.
 * Lit `metadata.required_fields` (array de keys) dans la définition du step et
 * vérifie que toutes ces keys sont non-empty dans le payload.
 */
class FormStepValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $required = (array) ($step->metadata['required_fields'] ?? []);
        $missing = [];
        foreach ($required as $key) {
            if (! isset($payload[$key]) || $payload[$key] === '' || $payload[$key] === null) {
                $missing[] = $key;
            }
        }

        if (! empty($missing)) {
            return OnboardingStepValidation::fail([
                'fields' => "Champs obligatoires manquants : " . implode(', ', $missing),
            ]);
        }

        return OnboardingStepValidation::pass(normalizedData: array_intersect_key($payload, array_flip($required)));
    }
}
