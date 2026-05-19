<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que l'utilisateur a une KycVerification approved
 * (voir [[kyc-module]] : table `kyc_verifications`, status `clear`, decision `approved`).
 */
class KycCheckValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        if (! Schema::hasTable('kyc_verifications')) {
            return OnboardingStepValidation::fail(['kyc' => 'Module KYC non installé.']);
        }

        $verification = DB::table('kyc_verifications')
            ->where('user_id', $user->id)
            ->whereIn('decision', ['approved'])
            ->whereIn('status', ['clear'])
            ->latest('updated_at')
            ->first();

        if (! $verification) {
            return OnboardingStepValidation::fail([
                'kyc' => 'Vérification KYC non approuvée. Démarre via /api/provider/kyc/start.',
            ]);
        }

        return OnboardingStepValidation::pass(metadata: [
            'kyc_verification_id' => (int) $verification->id,
        ]);
    }
}
