<?php

namespace App\Services\Provider;

use App\Models\AcademyCompletion;
use App\Models\AcademyCourse;
use App\Models\User;
use App\Services\Badges\ProviderBadgeEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** L'ACADÉMIE (E16) — apprendre, et que ça serve. */
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

    /** Marquer une formation terminée. */
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

    /** Ce que la réussite débloque — badge, et poids dans le matching. SOFT-FAIL DES DEUX CÔTÉS. */
    protected function appliquerLesEffets(User $prestataire, AcademyCourse $cours, AcademyCompletion $completion): void
    {
        if ($cours->specialty_bonus > 0) {
            try {
                // LE BONUS VIT SUR LE PROFIL, pas dans le moteur.
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
