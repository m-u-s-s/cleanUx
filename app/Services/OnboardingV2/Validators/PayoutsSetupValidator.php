<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/**
 * Vérifie que le prestataire peut recevoir des paiements par Stripe Connect.
 *
 * Ce validateur lisait `stripe_account_id`, `stripe_details_submitted` et
 * `stripe_payouts_enabled` — trois colonnes qui n'existent nulle part. Comme PHP rend `null` sur
 * une propriété absente, il échouait systématiquement, quel que soit l'état réel du compte.
 *
 * Corrigé une première fois vers `provider_profiles.stripe_connect_*` : ces colonnes existent
 * bien, mais personne ne les écrit — StripeConnectService écrit sur `users`. Le validateur
 * passait donc d'une source inexistante à une source morte, sans que rien ne le signale.
 *
 * Il s'appuie désormais sur `canReceiveStripeConnectPayments()`, seul endroit qui connaît les
 * deux emplacements et privilégie celui qui est réellement alimenté : une définition unique de
 * « ce prestataire peut être payé », partagée avec le chemin de paiement lui-même.
 */
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
