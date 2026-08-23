<?php

namespace App\Services\Onboarding;

use App\Models\KycVerification;
use App\Models\OnboardingProgress;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/** Où en est réellement le dossier d'un prestataire ? */
class ProviderDossierSummary
{
    public function __construct(protected ProviderDocumentRequirements $requirements) {}

    /**
     * Deux niveaux distincts, parce qu'ils répondent à deux questions différentes.
     *
     * @return array{
     * journey: array{percent: int, done: int, total: int, missing: array<int, string>},
     * documents: array{required: array<int, string>, missing: array<int, string>, rejected: array<int, string>, pending: array<int, string>},
     * identity: array{decision: ?string, verified: bool},
     * payouts: array{status: ?string, ready: bool},
     * is_complete: bool,
     * can_mark_verified: bool,
     * blockers: array<int, string>,
     * warnings: array<int, string>
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

        // UNE PIÈCE PÉRIMÉE NE VAUT PAS UNE PIÈCE FOURNIE.
        foreach ($documents['expired'] as $label) {
            $blockers[] = "Justificatif périmé : {$label}";
        }

        if (! $identity['verified']) {
            $blockers[] = 'Identité non vérifiée';
        }

        $warnings = [];

        foreach ($documents['pending'] as $label) {
            $warnings[] = "Justificatif à relire : {$label}";
        }

        foreach ($documents['expiring'] as $label) {
            $warnings[] = "Justificatif bientôt périmé : {$label}";
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
     * @return array{required: array<int, string>, missing: array<int, string>, rejected: array<int, string>, pending: array<int, string>, expired: array<int, string>, expiring: array<int, string>}
     */
    private function documentState(User $user): array
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            return ['required' => [], 'missing' => [], 'rejected' => [], 'pending' => [], 'expired' => [], 'expiring' => []];
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
        $expired = [];
        $expiring = [];
        $preavis = (int) Config::get('onboarding_documents.expiring_soon_days', 30);

        foreach ($this->requirements->for($user) as $requirement) {
            $required[] = $requirement['label'];

            $document = $documents->first(
                fn (ProviderOnboardingDocument $doc): bool => in_array($doc->document_type, $requirement['accepts'], true),
            );

            if (! $document) {
                $missing[] = $requirement['label'];

                continue;
            }

            // LA PÉREMPTION PASSE AVANT LE STATUT, et l'ordre n'est pas indifférent.
            if ($document->isExpired()) {
                $expired[] = $requirement['label'];

                continue;
            }

            // Un refus ne vaut pas dépôt : la pièce doit être remplacée avant l'approbation.
            match ($document->status) {
                ProviderOnboardingDocument::STATUS_REJECTED => $rejected[] = $requirement['label'],
                ProviderOnboardingDocument::STATUS_APPROVED => null,
                default => $pending[] = $requirement['label'],
            };

            // L'ÉCHÉANCE PROCHE est un AVERTISSEMENT, pas un blocage.
            if ($preavis > 0
                && $document->expires_at !== null
                && $document->expires_at->isBefore(Carbon::now()->addDays($preavis))) {
                $expiring[] = $requirement['label'];
            }
        }

        return [
            'required' => $required,
            'missing' => $missing,
            'rejected' => $rejected,
            'pending' => $pending,
            'expired' => $expired,
            'expiring' => $expiring,
        ];
    }

    /**
     * `verification_status` compte au même titre que la décision KYC : le module d'identité le pose lui-même sur une décision favorable, et un prestataire vérifié par une autre voie ne doit pas être redemandé.
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
            'verified' => $verification?->decision === KycVerification::DECISION_APPROVED
                || $user->providerProfile?->verification_status === 'verified',
        ];
    }

    /**
     * `canReceiveStripeConnectPayments()` fait foi : les colonnes `stripe_connect_*` existent sur `users` et sur `provider_profiles`, mais seule la première est écrite.
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
