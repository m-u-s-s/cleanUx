<?php

namespace App\Services\Nps;

use App\Models\Booking;
use App\Models\NpsResponse;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class NpsService
{
    public function submit(
        User $user,
        string $surveyCode,
        int $score,
        ?Booking $booking = null,
        ?string $comment = null,
        ?string $locale = null
    ): NpsResponse {
        if ($score < 0 || $score > 10) {
            throw ValidationException::withMessages(['score' => ['Le score doit être entre 0 et 10.']]);
        }

        // Idempotency : 1 réponse par (user, survey_code, booking) — dernier 30 jours
        $existing = NpsResponse::query()
            ->where('user_id', $user->id)
            ->where('survey_code', $surveyCode)
            ->when($booking, fn ($q) => $q->where('booking_id', $booking->id))
            ->where('created_at', '>=', now()->subDays(30))
            ->first();
        if ($existing) {
            $existing->update([
                'score' => $score,
                'category' => NpsResponse::categorize($score),
                'comment' => $comment,
            ]);
            return $existing->fresh();
        }

        return NpsResponse::query()->create([
            'user_id' => $user->id,
            'booking_id' => $booking?->id,
            'survey_code' => $surveyCode,
            'score' => $score,
            'category' => NpsResponse::categorize($score),
            'comment' => $comment,
            'locale' => $locale ?? $user->locale,
            'responded_at' => now(),
        ]);
    }

    /**
     * Calcule NPS score = % promoters - % detractors. Range -100..+100.
     */
    public function calculate(Carbon $since, Carbon $until, ?string $surveyCode = null): array
    {
        $query = NpsResponse::query()
            ->whereBetween('responded_at', [$since, $until])
            ->when($surveyCode, fn ($q) => $q->where('survey_code', $surveyCode));

        $total = (clone $query)->count();
        if ($total === 0) {
            return ['nps' => null, 'total' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0];
        }

        $promoters = (clone $query)->where('category', NpsResponse::CATEGORY_PROMOTER)->count();
        $detractors = (clone $query)->where('category', NpsResponse::CATEGORY_DETRACTOR)->count();
        $passives = (clone $query)->where('category', NpsResponse::CATEGORY_PASSIVE)->count();

        $nps = round((($promoters - $detractors) / $total) * 100, 1);

        return [
            'nps' => $nps,
            'total' => $total,
            'promoters' => $promoters,
            'passives' => $passives,
            'detractors' => $detractors,
            'promoter_percent' => round(($promoters / $total) * 100, 1),
            'detractor_percent' => round(($detractors / $total) * 100, 1),
        ];
    }
}
