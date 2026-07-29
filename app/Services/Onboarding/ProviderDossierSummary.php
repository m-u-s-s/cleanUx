<?php

namespace App\Services\Onboarding;

use App\Models\KycVerification;
use App\Models\OnboardingProgress;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Où en est réellement le dossier d'un prestataire ?
 *
 * Deux voies d'approbation coexistaient sans se parler. `ProviderOnboardingService::approveOnboarding()`
 * exige une pièce d'identité approuvée, une assurance approuvée et un compte Stripe actif.
 * `ProviderRegistrationsCenter::approve()` — l'écran par lequel passent en pratique les
 * inscriptions de l'app mobile — posait `status = 'active'` sans rien vérifier du tout : un
 * administrateur approuvait à l'aveugle un prestataire n'ayant franchi aucune vérification, et le
 * profil restait `verification_status = 'unverified'` indéfiniment.
 *
 * Cette classe est la lecture commune des deux voies : elle ne décide rien, elle constate. C'est
 * l'écran qui décide, en connaissance de cause.
 *
 * Stripe Connect est rapporté sans être bloquant, à dessein : un prestataire peut être vérifié
 * sans être encore configuré pour être payé, et l'exiger à l'inscription allongerait beaucoup le
 * parcours. C'est le choix déjà fait par ProviderOnboardingJourneySeeder.
 */
class ProviderDossierSummary
{
    public function __construct(protected ProviderDocumentRequirements $requirements) {}

    /**
     * Deux niveaux distincts, parce qu'ils répondent à deux questions différentes.
     *
     * `blockers` liste ce que le PRESTATAIRE n'a pas fait : une étape non franchie, une pièce
     * absente ou refusée, une identité non vérifiée. Approuver malgré cela est un passage en
     * force, qui doit être motivé.
     *
     * `warnings` liste ce que l'ADMINISTRATION n'a pas encore fait, ou ce qui n'empêche pas de
     * travailler : une pièce déposée mais pas encore relue, un compte de paiement pas encore
     * configuré. Exiger qu'une pièce soit relue avant d'approuver l'inscription créerait un
     * blocage circulaire — cet écran est souvent l'endroit d'où part la relecture.
     *
     * `can_mark_verified` est plus strict que `is_complete` : il exige que chaque pièce ait été
     * réellement APPROUVÉE, comme le fait approveOnboarding(). C'est lui qui autorise à écrire
     * `verification_status = 'verified'` — un statut qui affirme une vérification humaine.
     *
     * @return array{
     *     journey: array{percent: int, done: int, total: int, missing: array<int, string>},
     *     documents: array{required: array<int, string>, missing: array<int, string>, rejected: array<int, string>, pending: array<int, string>},
     *     identity: array{decision: ?string, verified: bool},
     *     payouts: array{status: ?string, ready: bool},
     *     is_complete: bool,
     *     can_mark_verified: bool,
     *     blockers: array<int, string>,
     *     warnings: array<int, string>
     * }
     */
    public function for(User $user): array
    {
        $journey = $this->journeyState($user);
        $documents = $this->documentState($user);
        $identity = $this->identityState($user);
        $payouts = $this->payoutState($user);

        $blockers = [];

        foreach ($journey['missing'] as $label) {
            $blockers[] = "Étape non terminée : {$label}";
        }

        foreach ($documents['missing'] as $label) {
            $blockers[] = "Justificatif manquant : {$label}";
        }

        foreach ($documents['rejected'] as $label) {
            $blockers[] = "Justificatif refusé : {$label}";
        }

        if (! $identity['verified']) {
            $blockers[] = 'Identité non vérifiée';
        }

        $warnings = [];

        foreach ($documents['pending'] as $label) {
            $warnings[] = "Justificatif à relire : {$label}";
        }

        if (! $payouts['ready']) {
            $warnings[] = 'Compte de paiement non configuré — le prestataire ne pourra pas être payé';
        }

        return [
            'journey' => $journey,
            'documents' => $documents,
            'identity' => $identity,
            'payouts' => $payouts,
            'is_complete' => $blockers === [],
            'can_mark_verified' => $blockers === [] && $documents['pending'] === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{percent: int, done: int, total: int, missing: array<int, string>}
     */
    private function journeyState(User $user): array
    {
        $progress = OnboardingProgress::query()
            ->with(['journey.steps', 'completions'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $progress || ! $progress->journey) {
            return ['percent' => 0, 'done' => 0, 'total' => 0, 'missing' => []];
        }

        $steps = $progress->journey->steps->where('required', true);
        $completed = $progress->completions
            ->whereIn('status', ['completed', 'skipped'])
            ->pluck('step_id')
            ->all();

        $missing = $steps
            ->reject(fn ($step) => in_array($step->id, $completed, true))
            ->map(fn ($step) => (string) ($step->label ?: $step->code))
            ->values()
            ->all();

        $total = $steps->count();
        $done = $total - count($missing);

        return [
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            'done' => $done,
            'total' => $total,
            'missing' => $missing,
        ];
    }

    /**
     * @return array{required: array<int, string>, missing: array<int, string>, rejected: array<int, string>, pending: array<int, string>}
     */
    private function documentState(User $user): array
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            return ['required' => [], 'missing' => [], 'rejected' => [], 'pending' => []];
        }

        $documents = ProviderOnboardingDocument::query()
            ->forUser($user->id)
            ->latest('id')
            ->get()
            ->unique('document_type');

        $required = [];
        $missing = [];
        $rejected = [];
        $pending = [];

        foreach ($this->requirements->for($user) as $requirement) {
            $required[] = $requirement['label'];

            $document = $documents->first(
                fn (ProviderOnboardingDocument $doc): bool => in_array($doc->document_type, $requirement['accepts'], true),
            );

            if (! $document) {
                $missing[] = $requirement['label'];

                continue;
            }

            // Un refus ne vaut pas dépôt : la pièce doit être remplacée avant l'approbation.
            match ($document->status) {
                ProviderOnboardingDocument::STATUS_REJECTED => $rejected[] = $requirement['label'],
                ProviderOnboardingDocument::STATUS_APPROVED => null,
                default => $pending[] = $requirement['label'],
            };
        }

        return ['required' => $required, 'missing' => $missing, 'rejected' => $rejected, 'pending' => $pending];
    }

    /**
     * `verification_status` compte au même titre que la décision KYC : le module d'identité le
     * pose lui-même sur une décision `clear`, et un prestataire vérifié par une autre voie ne
     * doit pas être redemandé. Ce n'est pas circulaire tant que l'approbation ne l'écrit que
     * lorsque le dossier le justifie — c'est précisément ce que garantit `can_mark_verified`.
     *
     * @return array{decision: ?string, verified: bool}
     */
    private function identityState(User $user): array
    {
        $verification = KycVerification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return [
            'decision' => $verification?->decision,
            'verified' => $verification?->decision === 'clear'
                || $user->providerProfile?->verification_status === 'verified',
        ];
    }

    /**
     * `canReceiveStripeConnectPayments()` fait foi : les colonnes `stripe_connect_*` existent sur
     * `users` et sur `provider_profiles`, mais seule la première est écrite. Lire le profil seul
     * rapporterait « non configuré » pour un compte Stripe pleinement actif.
     *
     * @return array{status: ?string, ready: bool}
     */
    private function payoutState(User $user): array
    {
        return [
            'status' => $user->stripe_connect_status ?? $user->providerProfile?->stripe_connect_status,
            'ready' => $user->canReceiveStripeConnectPayments(),
        ];
    }
}
