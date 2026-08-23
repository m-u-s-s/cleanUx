<?php

namespace Database\Seeders\Concerns;

use App\Models\KycVerification;
use App\Models\OnboardingJourney;
use App\Models\OnboardingProgress;
use App\Models\OnboardingStep;
use App\Models\User;
use App\Services\Kyc\KycVerificationService;
use App\Services\Onboarding\ProviderDocumentRequirements;
use App\Services\OnboardingV2\OnboardingEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** BOUCLER LE DOSSIER D'INSCRIPTION D'UN PRESTATAIRE SEMÉ. */
trait BoucleLeDossierPrestataire
{
    protected function boucleLeDossier(User $prestataire): void
    {
        if (! Schema::hasTable('kyc_verifications') || ! Schema::hasTable('onboarding_journeys')) {
            return;
        }

        $this->identiteVerifiee($prestataire);
        $this->justificatifsDeposes($prestataire);
        $this->parcoursTermine($prestataire);
    }

    private function identiteVerifiee(User $prestataire): void
    {
        $dejaApprouve = KycVerification::query()
            ->where('user_id', $prestataire->id)
            ->where('decision', KycVerification::DECISION_APPROVED)
            ->exists();

        if ($dejaApprouve) {
            return;
        }

        $service = app(KycVerificationService::class);
        $service->syncStatus($service->start($prestataire, 'BE'));
    }

    /** Les justificatifs que les métiers déclarés rendent obligatoires. */
    private function justificatifsDeposes(User $prestataire): void
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            return;
        }

        foreach (app(ProviderDocumentRequirements::class)->requiredTypesFor($prestataire) as $type) {
            DB::table('provider_onboarding_documents')->updateOrInsert(
                ['user_id' => $prestataire->id, 'document_type' => $type],
                [
                    'status' => 'approved',
                    'file_path' => "demo/onboarding/{$type}.pdf",
                    'file_name' => "{$type}-demonstration.pdf",
                    'mime_type' => 'application/pdf',
                    'file_size' => 1024,
                    'reviewed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /** Fait franchir au prestataire chaque étape de son parcours, validateur compris. */
    private function parcoursTermine(User $prestataire): void
    {
        $parcours = OnboardingJourney::query()->where('role', 'provider')->first();

        if (! $parcours) {
            return;
        }

        $moteur = app(OnboardingEngine::class);
        $progression = $moteur->startFor($prestataire);

        $etapes = OnboardingStep::query()
            ->where('journey_id', $parcours->id)
            ->orderBy('position')
            ->get();

        foreach ($etapes as $etape) {
            // IDEMPOTENT.
            if ($progression->fresh()?->status === OnboardingProgress::STATUS_COMPLETED) {
                return;
            }

            try {
                $moteur->markComplete($progression, $etape, $this->chargeUtile($etape, $prestataire), $prestataire);
            } catch (\Throwable $e) {
                // Une étape qui refuse est une INFORMATION, pas un incident : elle dit qu'une donnée manque encore en base.
                $this->command?->warn("⚠️ Étape « {$etape->code} » non validée pour {$prestataire->email} : ".$e->getMessage());
            }
        }
    }

    /**
     * Ce que chaque étape attend du porteur.
     *
     * @return array<string, mixed>
     */
    private function chargeUtile(OnboardingStep $etape, User $prestataire): array
    {
        return match ($etape->code) {
            // `terms_accepted_version` et non `accepted_version` : la seconde est ce que le validateur REND une fois satisfait, pas ce qu'il attend.
            'contract_sign' => [
                'terms_accepted_version' => (string) ($etape->metadata['required_version'] ?? '1.0'),
            ],
            'skill_declare' => ['trade_codes' => $prestataire->trades()->pluck('code')->all()],
            default => [],
        };
    }
}
