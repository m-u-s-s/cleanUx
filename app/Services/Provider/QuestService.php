<?php

namespace App\Services\Provider;

use App\Models\Mission;
use App\Models\ProviderQuest;
use App\Models\ProviderQuestProgress;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** LES OBJECTIFS D'UN PRESTATAIRE (E13). */
class QuestService
{
    /**
     * Recalculer où en est quelqu'un, sur toutes les quêtes en cours.
     *
     * @return list<array<string, mixed>>
     */
    public function pour(User $prestataire): array
    {
        $quetes = ProviderQuest::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (ProviderQuest $quete) => $quete->estEnCours());

        $lignes = [];

        foreach ($quetes as $quete) {
            $avancement = $this->mesurer($prestataire, $quete);
            $ligne = $this->enregistrer($prestataire, $quete, $avancement);

            $lignes[] = [
                'quest_id' => $quete->id,
                'code' => $quete->code,
                'title' => $quete->title,
                'description' => $quete->description,
                'target' => $quete->target,
                'progress' => $avancement,
                // CE QU'IL RESTE, en clair : c'est le seul chiffre qui fait faire la course de trop.
                'remaining' => max(0, $quete->target - $avancement),
                'percent' => $quete->target > 0
                    ? min(100, (int) round($avancement / $quete->target * 100))
                    : 0,
                'is_completed' => $ligne->completed_at !== null,
                'is_rewarded' => $ligne->rewarded_at !== null,
                'reward_type' => $quete->reward_type,
                'reward_value' => $quete->reward_value,
                'ends_on' => $quete->ends_on?->toDateString(),
            ];
        }

        return $lignes;
    }

    /** Compter ce qui compte, DEPUIS LA SOURCE. */
    protected function mesurer(User $prestataire, ProviderQuest $quete): int
    {
        $requete = Mission::query()
            ->where('lead_provider_user_id', $prestataire->id)
            ->whereIn('status', ['completed', 'termine', 'terminee', 'done'])
            ->when($quete->starts_on, fn ($q) => $q->where('created_at', '>=', $quete->starts_on->startOfDay()))
            ->when($quete->ends_on, fn ($q) => $q->where('created_at', '<=', $quete->ends_on->endOfDay()));

        return match ($quete->metric) {
            ProviderQuest::METRIC_MISSIONS => $requete->count(),
            // Les autres métriques ne sont pas encore mesurées : rendre zéro est HONNÊTE — un
            // compteur inventé ferait afficher une progression que rien ne soutient.
            default => 0,
        };
    }

    protected function enregistrer(User $prestataire, ProviderQuest $quete, int $avancement): ProviderQuestProgress
    {
        return DB::transaction(function () use ($prestataire, $quete, $avancement) {
            /** @var ProviderQuestProgress $ligne */
            $ligne = ProviderQuestProgress::query()->firstOrNew([
                'provider_quest_id' => $quete->id,
                'user_id' => $prestataire->id,
            ]);

            $ligne->progress = $avancement;

            if ($avancement >= $quete->target && $ligne->completed_at === null) {
                $ligne->completed_at = now();
            }

            $ligne->save();

            // Atteindre et être payé sont deux événements : la récompense se verse une fois, et
            // seulement après la complétion.
            if ($ligne->completed_at !== null && $ligne->rewarded_at === null) {
                $this->recompenser($prestataire, $quete, $ligne);
            }

            return $ligne;
        });
    }

    /** Verser la récompense — par les modules existants. SOFT-FAIL, ET LA MARQUE D'ABORD. */
    protected function recompenser(User $prestataire, ProviderQuest $quete, ProviderQuestProgress $ligne): void
    {
        if ($quete->reward_type !== ProviderQuest::REWARD_LOYALTY || $quete->reward_value <= 0) {
            return;
        }

        try {
            app(LoyaltyService::class)->award(
                user: $prestataire,
                type: 'quest_completed',
                points: $quete->reward_value,
                source: $quete,
                // LA CLÉ D'IDEMPOTENCE PORTE LA QUÊTE ET LA PERSONNE, jamais l'horodatage : ce service RECALCULE à chaque lecture, et une clé variable verserait la récompense autant de fois que l'écran est ouvert.
                idempotencyKey: 'quest:'.$quete->code.':'.$prestataire->id,
                reason: $quete->title,
            );

            $ligne->forceFill(['rewarded_at' => now()])->save();
        } catch (\Throwable $e) {
            // La quête reste complétée et non récompensée : la prochaine lecture réessaiera.
            Log::warning('[quests] récompense non versée', [
                'quest' => $quete->code,
                'user_id' => $prestataire->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
