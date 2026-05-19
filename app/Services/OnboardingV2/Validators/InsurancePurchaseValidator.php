<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que l'utilisateur a une BookingInsurance active OU a accepté
 * les terms d'assurance par défaut (cf payload['accepted_default_terms']).
 */
class InsurancePurchaseValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        // Option 1 : a une BookingInsurance active
        if (Schema::hasTable('booking_insurances')) {
            $active = DB::table('booking_insurances')
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('provider_user_id', $user->id);
                })
                ->where('status', 'active')
                ->exists();
            if ($active) {
                return OnboardingStepValidation::pass(metadata: ['source' => 'existing_policy']);
            }
        }

        // Option 2 : a accepté les terms par défaut (couverture plateforme générique)
        if (! empty($payload['accepted_default_terms'])) {
            return OnboardingStepValidation::pass(
                normalizedData: ['accepted_default_terms' => true, 'accepted_at' => now()->toIso8601String()],
                metadata: ['source' => 'default_terms'],
            );
        }

        return OnboardingStepValidation::fail([
            'insurance' => 'Aucune couverture active et terms par défaut non acceptés.',
        ]);
    }
}
