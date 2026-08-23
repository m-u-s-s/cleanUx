<?php

namespace App\Services\OnboardingV2\Validators;

use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\OnboardingV2\OnboardingStepValidation;
use App\Services\OnboardingV2\OnboardingStepValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Vérifie qu'un type de document a été uploadé via le system existant (provider_onboarding_documents, phase 14.1). */
class DocumentUploadValidator implements OnboardingStepValidator
{
    public function validate(User $user, OnboardingStep $step, array $payload): OnboardingStepValidation
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            return OnboardingStepValidation::fail(['documents' => 'Module documents non disponible.']);
        }

        $requirements = app(ProviderDocumentRequirements::class);

        // `document_types` fixait la liste dans la définition du parcours : tout le monde devait
        // la même pièce d'identité, et personne son assurance — alors qu'approveOnboarding() en
        // exige une pour valider le dossier. La liste se dérive donc des métiers déclarés.
        // Le mode statique reste possible pour un parcours qui l'imposerait explicitement.
        $requiredTypes = (array) ($step->metadata['document_types'] ?? []);
        if (empty($requiredTypes)) {
            $requiredTypes = $requirements->requiredTypesFor($user);
        }

        if (empty($requiredTypes)) {
            return OnboardingStepValidation::pass();
        }

        // Une pièce d'identité vaut carte, passeport ou titre de séjour : la présence est donc
        // évaluée sur l'ensemble des types acceptés, pas sur une correspondance exacte.
        $presentTypes = DB::table('provider_onboarding_documents')
            ->where('user_id', $user->id)
            ->whereIn('status', ['uploaded', 'pending_review', 'approved'])
            ->pluck('document_type')
            ->all();

        $missing = array_values(array_filter(
            $requiredTypes,
            static fn (string $type): bool => ! $requirements->isSatisfied($type, $presentTypes),
        ));

        if (! empty($missing)) {
            return OnboardingStepValidation::fail([
                'documents' => 'Documents manquants : '.implode(', ', array_map(
                    static fn (string $type): string => (string) config("onboarding_documents.labels.{$type}.label", $type),
                    $missing,
                )),
            ]);
        }

        return OnboardingStepValidation::pass(metadata: ['types_verified' => $requiredTypes]);
    }
}
