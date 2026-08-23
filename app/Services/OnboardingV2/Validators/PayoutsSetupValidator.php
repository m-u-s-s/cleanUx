<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/** Vérifie que le prestataire peut recevoir des paiements par Stripe Connect. */
class PayoutsSetupValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $accountId = $user->stripe_connect_account_id
            ?? $user->providerProfile?->stripe_connect_account_id;

        if (! $accountId) {
            return OnboardingStepValidation::fail([
                'payouts' => 'Compte Stripe Connect non lié. Lancez la configuration des paiements.',
            ]);
        }

        // Un compte existe dès le début du parcours Stripe : seul son aboutissement atteste
        // qu'il peut recevoir des fonds.
        if (! $user->canReceiveStripeConnectPayments()) {
            return OnboardingStepValidation::fail([
                'payouts' => 'Configuration Stripe Connect non finalisée : vos paiements ne sont pas encore actifs.',
            ]);
        }

        return OnboardingStepValidation::pass(metadata: [
            'stripe_connect_account_id' => $accountId,
        ]);
    }
}
