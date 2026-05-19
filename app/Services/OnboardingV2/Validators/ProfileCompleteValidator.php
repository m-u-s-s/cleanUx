<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/**
 * Vérifie que les champs minimum du user sont remplis :
 *   - name, email (toujours)
 *   - phone (toujours pour provider, optionnel pour client)
 *   - locale (toujours)
 *
 * Liste configurable via step.metadata.required_user_fields[].
 */
class ProfileCompleteValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $required = (array) ($step->metadata['required_user_fields'] ?? ['name', 'email', 'locale']);
        $missing = [];
        foreach ($required as $field) {
            $val = $user->{$field} ?? null;
            if ($val === null || $val === '') {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            return OnboardingStepValidation::fail([
                'profile' => 'Champs manquants sur le profil : ' . implode(', ', $missing),
            ]);
        }

        return OnboardingStepValidation::pass();
    }
}
