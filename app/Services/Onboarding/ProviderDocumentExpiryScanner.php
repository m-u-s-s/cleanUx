<?php

namespace App\Services\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Notifications\ProviderDocumentExpiringNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * PRÉVENIR AVANT L'ÉCHÉANCE — pendant qu'il est encore temps d'agir.
 *
 * Une pièce qui expire ne prévient personne : le prestataire l'apprend au silence de son téléphone,
 * plusieurs jours après, et le support par un appel agacé. C'est l'angle mort de cette plateforme —
 * le compte actif dont plus rien ne part — transposé aux DATES.
 *
 * IL NE CHANGE AUCUN STATUT, et c'est délibéré. `status` décrit une RELECTURE (déposé, approuvé,
 * refusé) ; la péremption est un FAIT DE DATE. Écrire « expiré » dans la colonne de statut ferait
 * dire deux choses à un même champ, et il faudrait ensuite savoir lequel des deux sens s'applique —
 * le défaut dominant de ce dépôt. La péremption se DÉDUIT donc de `expires_at`, partout et de la
 * même façon : dans le verrou de dispatch (en SQL) comme dans le dossier (en PHP).
 *
 * IL NE PRÉVIENT QU'UNE FOIS PAR ÉCHÉANCE. Sans cette garde, un cron quotidien enverrait trente
 * courriels pour un même permis — après quoi plus personne ne les lit, y compris le trente et
 * unième qui compte. La trace vit dans `metadata`, la colonne qui existait et que rien n'écrivait.
 */
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
            /*
             * Seules les pièces APPROUVÉES sont concernées. Une pièce refusée doit être redéposée de
             * toute façon, et une pièce en relecture le sera peut-être avant l'échéance : alerter
             * sur celles-là ajouterait du bruit à un dossier déjà en mouvement.
             */
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

    /**
     * A-t-on déjà prévenu POUR CETTE ÉCHÉANCE-LÀ ?
     *
     * La date est comparée, pas un simple drapeau : renouveler une pièce lui donne une nouvelle
     * échéance, et un booléen empêcherait alors de prévenir une seconde fois — trois ans plus tard,
     * quand la question se reposera exactement à l'identique.
     */
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
