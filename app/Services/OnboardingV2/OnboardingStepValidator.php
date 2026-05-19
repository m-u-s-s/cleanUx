<?php

namespace App\Services\OnboardingV2;

use App\Models\OnboardingStep;
use App\Models\User;

interface OnboardingStepValidator
{
    /**
     * @param array<string,mixed> $payload  Payload submitted by the user (form fields, etc.)
     */
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation;
}
