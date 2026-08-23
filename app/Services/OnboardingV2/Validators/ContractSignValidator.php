<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\ContractTemplate;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\ContractsV2\ContractService;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\Schema;

/** Validator pour le step `contract_sign` (e.g. TOS, provider_agreement). */
class ContractSignValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        $templateCode = (string) ($step->metadata['template_code'] ?? '');

        if ($templateCode && class_exists(ContractService::class)) {
            $svc = app(ContractService::class);

            if ($svc->userHasValidSignatureFor($user, $templateCode)) {
                return OnboardingStepValidation::pass(
                    normalizedData: ['template_code' => $templateCode],
                    metadata: ['source' => 'contracts_v2'],
                );
            }

            // Le modèle référencé n'existe pas dans cet environnement : exiger une signature
            // contre un contrat introuvable rendrait l'étape définitivement infranchissable.
            // On retombe alors sur la version, chemin autonome — un déploiement où les modèles
            // ne sont pas seedés doit rester praticable, pas enfermer le prestataire.
            if ($this->templateExists($templateCode)) {
                return OnboardingStepValidation::fail([
                    'contract' => "Aucune signature valide trouvée pour '{$templateCode}'.",
                ]);
            }
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

    /** Défensif sur le schéma comme le reste du module : la table peut manquer d'un déploiement où Contracts v2 n'est pas installé, auquel cas il n'y a évidemment aucun modèle. */
    private function templateExists(string $templateCode): bool
    {
        if (! Schema::hasTable('contract_templates')) {
            return false;
        }

        return ContractTemplate::query()->where('code', $templateCode)->active()->exists();
    }
}
