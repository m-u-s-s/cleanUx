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

/**
 * BOUCLER LE DOSSIER D'INSCRIPTION D'UN PRESTATAIRE SEMÉ.
 *
 * DEUX SOURCES RÉPONDENT À « CE PRESTATAIRE EST-IL VÉRIFIÉ ? », ET LES DEUX CÔTÉS DE L'APPLICATION
 * N'INTERROGENT PAS LA MÊME : le dispatch lit `provider_profiles.verification_status`, l'onboarding
 * lit `kyc_verifications`. Les seeders ne posaient que la première. Résultat : le prestataire
 * recevait bien les offres — le moteur fonctionnait — et se heurtait à « Vérification KYC non
 * approuvée » dès qu'il ouvrait son espace. La démonstration s'arrêtait donc juste après la
 * connexion, et l'écran accusait le KYC là où le trou était dans le semis.
 *
 * ON PASSE PAR LES VRAIS CHEMINS plutôt que d'insérer des lignes à la main. Le fournisseur KYC de
 * développement approuve toute adresse ne portant ni « reject » ni « review », et
 * `OnboardingEngine::markComplete()` REJOUE chaque validateur : si l'un refuse, c'est qu'une donnée
 * manque vraiment, et le seeder le dit au lieu de fabriquer un état incohérent que l'écran
 * démentirait.
 *
 * TOUT EST DÉFENSIF SUR LE SCHÉMA, comme le reste de ces modules : un déploiement sans KYC ni
 * onboarding doit rester semable.
 */
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

    /**
     * Les justificatifs que les métiers déclarés rendent obligatoires.
     *
     * La liste se DÉRIVE des métiers (`ProviderDocumentRequirements`) : la figer ici ferait diverger
     * le seeder du jour où un métier exigera une pièce de plus, et l'étape resterait bloquée sans
     * qu'on sache laquelle manque. Aucun fichier n'est déposé — c'est l'ÉTAT du dossier qu'on sème,
     * pas un faux document.
     */
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
            /*
             * IDEMPOTENT. Rejouer un seeder est le geste courant — c'est ainsi qu'on remet les
             * prestataires de démonstration en ligne après une pause. Sans ce saut, un dossier déjà
             * bouclé ressortait une volée d'avertissements « Journey déjà complet », qui donnent
             * l'apparence d'une panne là où tout est en ordre.
             */
            if ($progression->fresh()?->status === OnboardingProgress::STATUS_COMPLETED) {
                return;
            }

            try {
                $moteur->markComplete($progression, $etape, $this->chargeUtile($etape, $prestataire), $prestataire);
            } catch (\Throwable $e) {
                /*
                 * Une étape qui refuse est une INFORMATION, pas un incident : elle dit qu'une donnée
                 * manque encore en base. On le signale et on continue — laisser le seeder échouer
                 * priverait la démonstration de tout le reste, et l'avaler en silence ramènerait le
                 * mur d'aujourd'hui.
                 */
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
            /*
             * `terms_accepted_version` et non `accepted_version` : la seconde est ce que le
             * validateur REND une fois satisfait, pas ce qu'il attend. Les confondre donnait
             * « Version contrat manquante » sur une charge utile qui semblait pourtant complète.
             */
            'contract_sign' => [
                'terms_accepted_version' => (string) ($etape->metadata['required_version'] ?? '1.0'),
            ],
            'skill_declare' => ['trade_codes' => $prestataire->trades()->pluck('code')->all()],
            default => [],
        };
    }
}
