<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;

/**
 * Validator pour le step `contract_sign` (e.g. TOS, provider_agreement).
 *
 * Si `step.metadata.template_code` est défini ET ContractsV2 module installé,
 * on vérifie via `ContractService::userHasValidSignatureFor()` (vraie signature DB).
 *
 * Fallback legacy : sans template_code, on accepte
 * `payload['terms_accepted_version']` matchant `step.metadata.required_version`
 * (compatibilité avec tests onboarding existants).
 */
class ContractSignValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $templateCode = (string) ($step->metadata['template_code'] ?? '');

        if ($templateCode && class_exists(\App\Services\ContractsV2\ContractService::class)) {
            $svc = app(\App\Services\ContractsV2\ContractService::class);
            if ($svc->userHasValidSignatureFor($user, $templateCode)) {
                return OnboardingStepValidation::pass(
                    normalizedData: ['template_code' => $templateCode],
                    metadata: ['source' => 'contracts_v2'],
                );
            }
            return OnboardingStepValidation::fail([
                'contract' => "Aucune signature valide trouvée pour '{$templateCode}'.",
            ]);
        }

        $requiredVersion = (string) ($step->metadata['required_version'] ?? '');
        $accepted = (string) ($payload['terms_accepted_version'] ?? '');

        if ($requiredVersion === '' || $accepted === '') {
            return OnboardingStepValidation::fail(['contract' => 'Version contrat manquante.']);
        }
        if ($accepted !== $requiredVersion) {
            return OnboardingStepValidation::fail([
                'contract' => "Version contrat acceptée ({$accepted}) ≠ version requise ({$requiredVersion}).",
            ]);
        }

        return OnboardingStepValidation::pass(
            normalizedData: ['accepted_version' => $accepted, 'accepted_at' => now()->toIso8601String()],
            metadata: ['source' => 'legacy_payload'],
        );
    }
}
