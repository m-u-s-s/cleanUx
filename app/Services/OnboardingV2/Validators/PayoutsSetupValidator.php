<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que le provider a un Stripe Connect account onboardé.
 * Sa source de vérité : `provider_profiles.stripe_account_id` + flag onboarding completed.
 */
class PayoutsSetupValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        if (! Schema::hasTable('provider_profiles')) {
            return OnboardingStepValidation::fail(['payouts' => 'Profil provider introuvable.']);
        }

        $profile = DB::table('provider_profiles')->where('user_id', $user->id)->first();

        if (! $profile) {
            return OnboardingStepValidation::fail(['payouts' => 'Profil provider non créé.']);
        }

        $accountId = $profile->stripe_account_id ?? null;
        if (! $accountId) {
            return OnboardingStepValidation::fail([
                'payouts' => 'Compte Stripe Connect non lié. Lance /api/provider/onboarding/start pour générer le link.',
            ]);
        }

        // Check if onboarding flagged complete (column may not exist on legacy schema)
        $detailsSubmitted = $profile->stripe_details_submitted ?? null;
        $payoutsEnabled = $profile->stripe_payouts_enabled ?? null;
        if ($detailsSubmitted === false || $payoutsEnabled === false) {
            return OnboardingStepValidation::fail([
                'payouts' => 'Onboarding Stripe Connect non finalisé (details ou payouts pas enabled).',
            ]);
        }

        return OnboardingStepValidation::pass(metadata: [
            'stripe_account_id' => $accountId,
        ]);
    }
}
