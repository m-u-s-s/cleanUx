<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vérifie qu'un type de document a été uploadé via le system existant
 * (provider_onboarding_documents, phase 14.1).
 *
 * step.metadata.document_types[] : liste de types requis (ex: ['id_card', 'insurance_proof']).
 */
class DocumentUploadValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            return OnboardingStepValidation::fail(['documents' => 'Module documents non disponible.']);
        }

        $requiredTypes = (array) ($step->metadata['document_types'] ?? []);
        if (empty($requiredTypes)) {
            return OnboardingStepValidation::pass();
        }

        $missing = [];
        foreach ($requiredTypes as $type) {
            $exists = DB::table('provider_onboarding_documents')
                ->where('user_id', $user->id)
                ->where('document_type', $type)
                ->whereIn('status', ['uploaded', 'pending_review', 'approved'])
                ->exists();
            if (! $exists) {
                $missing[] = $type;
            }
        }

        if (! empty($missing)) {
            return OnboardingStepValidation::fail([
                'documents' => 'Documents manquants : ' . implode(', ', $missing),
            ]);
        }

        return OnboardingStepValidation::pass(metadata: ['types_verified' => $requiredTypes]);
    }
}
