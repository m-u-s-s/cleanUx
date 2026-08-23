<?php

namespace App\Services\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Notifications\ProviderDocumentExpiringNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/** PRÉVENIR AVANT L'ÉCHÉANCE — pendant qu'il est encore temps d'agir. */
class ProviderDocumentExpiryScanner
{
    /**
     * @return array{notified: int, expired: int, skipped: int}
     */
    public function scanAndNotify(?int $days = null): array
    {
        $preavis = $days ?? (int) Config::get('onboarding_documents.expiring_soon_days', 30);

        if ($preavis <= 0) {
            return ['notified' => 0, 'expired' => 0, 'skipped' => 0];
        }

        $aujourdhui = Carbon::today();
        $limite = $aujourdhui->copy()->addDays($preavis);

        $compte = ['notified' => 0, 'expired' => 0, 'skipped' => 0];

        ProviderOnboardingDocument::query()
            ->whereNotNull('expires_at')
            // Seules les pièces APPROUVÉES sont concernées.
            ->where('status', ProviderOnboardingDocument::STATUS_APPROVED)
            ->whereDate('expires_at', '<=', $limite->toDateString())
            ->with('user')
            ->chunkById(200, function ($documents) use (&$compte, $aujourdhui) {
                foreach ($documents as $document) {
                    if ($document->expires_at->lt($aujourdhui)) {
                        // Déjà périmée : le dossier la signale et le dispatch l'exclut déjà. On la
                        // compte pour l'exploitation, on ne relance pas — l'alerte est passée.
                        $compte['expired']++;

                        continue;
                    }

                    if ($this->dejaPrevenu($document)) {
                        $compte['skipped']++;

                        continue;
                    }

                    if (! $document->user) {
                        $compte['skipped']++;

                        continue;
                    }

                    try {
                        $document->user->notify(new ProviderDocumentExpiringNotification($document));
                        $this->marquerPrevenu($document);
                        $compte['notified']++;
                    } catch (\Throwable $e) {
                        // Soft-fail : une messagerie indisponible ne doit pas interrompre le scan et
                        // priver d'alerte tous les documents suivants.
                        $compte['skipped']++;
                        Log::warning('[onboarding] préavis de péremption non envoyé', [
                            'document_id' => $document->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $compte;
    }

    /** A-t-on déjà prévenu POUR CETTE ÉCHÉANCE-LÀ ? */
    private function dejaPrevenu(ProviderOnboardingDocument $document): bool
    {
        return ($document->metadata['expiry_notified_for'] ?? null) === $document->expires_at->toDateString();
    }

    private function marquerPrevenu(ProviderOnboardingDocument $document): void
    {
        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'expiry_notified_for' => $document->expires_at->toDateString(),
                'expiry_notified_at' => Carbon::now()->toIso8601String(),
            ]),
        ])->save();
    }
}
