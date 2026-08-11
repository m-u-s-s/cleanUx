<?php

namespace App\Services\Provider;

use App\Models\AcademyCompletion;
use App\Models\AcademyCourse;
use App\Models\User;
use App\Services\Badges\ProviderBadgeEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L'ACADÉMIE (E16) — apprendre, et que ça serve.
 *
 * RÉUSSIR DOIT CHANGER QUELQUE CHOSE, sinon personne ne suit. C'est la seule question qui vaille
 * pour un module de formation : un catalogue de cours sans effet est un catalogue que personne
 * n'ouvre deux fois. Ici, une complétion débloque un badge existant ET pèse dans le scoring de
 * matching — deux effets visibles, l'un par le client, l'autre dans le nombre d'offres reçues.
 *
 * LE BONUS DE SPÉCIALITÉ VIT SUR LE PROFIL, pas dans le moteur de matching. Le moteur lit déjà les
 * spécialités : y ajouter une lecture de l'académie ferait deux endroits à maintenir, et l'un des
 * deux finirait par ne plus refléter l'autre.
 *
 * ON NE TERMINE PAS DEUX FOIS. La contrainte d'unicité le garantit en base ; le service rend la
 * complétion existante plutôt que d'échouer, parce qu'un double clic sur « j'ai terminé » n'est pas
 * une erreur de l'utilisateur.
 */
class AcademyService
{
    /**
     * Le catalogue vu par un prestataire, avec ce qu'il a déjà fait.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(User $prestataire): array
    {
        $faites = AcademyCompletion::query()
            ->where('user_id', $prestataire->id)
            ->pluck('completed_at', 'academy_course_id');

        return AcademyCourse::query()
            ->where('is_published', true)
            ->with('trade:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn (AcademyCourse $cours) => [
                'id' => $cours->id,
                'code' => $cours->code,
                'title' => $cours->title,
                'summary' => $cours->summary,
                'trade_name' => $cours->trade?->name,
                'duration_minutes' => $cours->duration_minutes,
                // CE QUE ÇA RAPPORTE, annoncé AVANT : c'est ce qui décide de commencer.
                'badge_code' => $cours->badge_code,
                'specialty_bonus' => $cours->specialty_bonus,
                'completed_at' => $faites[$cours->id]?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Marquer une formation terminée.
     *
     * REJOUABLE : un double clic sur « j'ai terminé » n'est pas une erreur de l'utilisateur, et
     * échouer lui donnerait l'impression d'avoir perdu son travail.
     */
    public function terminer(User $prestataire, AcademyCourse $cours, ?int $score = null): AcademyCompletion
    {
        return DB::transaction(function () use ($prestataire, $cours, $score) {
            $existante = AcademyCompletion::query()
                ->where('academy_course_id', $cours->id)
                ->where('user_id', $prestataire->id)
                ->first();

            if ($existante !== null) {
                return $existante;
            }

            /** @var AcademyCompletion $completion */
            $completion = AcademyCompletion::query()->create([
                'academy_course_id' => $cours->id,
                'user_id' => $prestataire->id,
                'completed_at' => now(),
                'score_percent' => $score,
            ]);

            $this->appliquerLesEffets($prestataire, $cours, $completion);

            return $completion->fresh();
        });
    }

    /**
     * Ce que la réussite débloque — badge, et poids dans le matching.
     *
     * SOFT-FAIL DES DEUX CÔTÉS. La complétion est acquise : un module de badges indisponible ne doit
     * pas la faire perdre. `badge_granted_at` reste nul, et une réévaluation ultérieure rattrapera.
     */
    protected function appliquerLesEffets(User $prestataire, AcademyCourse $cours, AcademyCompletion $completion): void
    {
        if ($cours->specialty_bonus > 0) {
            try {
                /*
                 * LE BONUS VIT SUR LE PROFIL, pas dans le moteur. Le moteur lit déjà les spécialités :
                 * y ajouter une lecture de l'académie ferait deux endroits à maintenir, et l'un des
                 * deux finirait par ne plus refléter l'autre.
                 */
                $profil = $prestataire->providerProfile;

                if ($profil !== null) {
                    $metadata = (array) ($profil->metadata ?? []);
                    $acquis = (array) data_get($metadata, 'academy.specialty_bonus', []);
                    $acquis[$cours->code] = $cours->specialty_bonus;
                    data_set($metadata, 'academy.specialty_bonus', $acquis);

                    $profil->forceFill(['metadata' => $metadata])->save();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (! $cours->badge_code) {
            return;
        }

        try {
            app(ProviderBadgeEngine::class)->evaluate($prestataire);

            $completion->forceFill(['badge_granted_at' => now()])->save();
        } catch (\Throwable $e) {
            // La complétion est acquise : `badge_granted_at` reste nul, une réévaluation ultérieure
            // rattrapera.
            Log::warning('[academy] badge non évalué', [
                'course' => $cours->code,
                'user_id' => $prestataire->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
