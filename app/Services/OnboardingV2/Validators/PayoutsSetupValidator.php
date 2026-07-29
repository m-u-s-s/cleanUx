<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie que le provider a un compte Stripe Connect onboardé.
 *
 * Ce validateur lisait `stripe_account_id`, `stripe_details_submitted` et
 * `stripe_payouts_enabled` — TROIS colonnes qui n'existent pas sur `provider_profiles`. Comme
 * PHP rend `null` sur une propriété absente d'un objet stdClass issu du Query Builder, il
 * échouait donc systématiquement, quel que soit l'état réel du compte Stripe.
 *
 * Les vraies colonnes sont `stripe_connect_account_id`, `stripe_connect_status` et
 * `stripe_connect_onboarded_at` (voir ProviderProfile::isStripeConnected(), qui fait déjà foi
 * ailleurs dans le code).
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

        $accountId = $profile->stripe_connect_account_id ?? null;
        if (! $accountId) {
            return OnboardingStepValidation::fail([
                'payouts' => 'Compte Stripe Connect non lié. Lancez la configuration des paiements.',
            ]);
        }

        // Même critère que ProviderProfile::isStripeConnected() : un compte existe dès le début
        // de l'onboarding Stripe, seul le statut `active` atteste qu'il peut recevoir des fonds.
        if (($profile->stripe_connect_status ?? null) !== 'active') {
            return OnboardingStepValidation::fail([
                'payouts' => 'Configuration Stripe Connect non finalisée : vos paiements ne sont pas encore actifs.',
            ]);
        }

        return OnboardingStepValidation::pass(metadata: [
            'stripe_connect_account_id' => $accountId,
        ]);
    }
}
