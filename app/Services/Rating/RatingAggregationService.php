<?php

namespace App\Services\Rating;

use App\Models\Feedback;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

class RatingAggregationService
{
    /**
     * Recompute rating aggregates for a single provider user.
     * Considers only client→provider ratings that are PUBLISHED and not hidden.
     */
    public function recalculateForProvider(int $providerUserId): void
    {
        $profile = ProviderProfile::query()
            ->where('user_id', $providerUserId)
            ->first();

        if (! $profile) {
            return;
        }

        $rows = Feedback::query()
            ->where('employe_id', $providerUserId)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->where('status', Feedback::STATUS_PUBLISHED)
            ->where('is_hidden', false)
            ->where('is_public', true)
            ->get([
                'rating',
                'note',
                'punctuality_score',
                'quality_score',
                'communication_score',
                'value_score',
                'published_at',
            ]);

        $count = $rows->count();

        if ($count === 0) {
            $profile->update([
                'rating_avg' => null,
                'rating_count' => 0,
                'rating_distribution' => null,
                'rating_dimensions' => null,
                'rating_last_at' => null,
            ]);
            return;
        }

        $values = $rows
            ->map(fn ($r) => (int) ($r->rating ?? $r->note))
            ->filter(fn ($v) => $v >= 1 && $v <= 5);

        $avg = $values->count() > 0
            ? round((float) $values->avg(), 2)
            : null;

        $distribution = [
            '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0,
        ];
        foreach ($values as $v) {
            $distribution[(string) $v] = ($distribution[(string) $v] ?? 0) + 1;
        }

        $dimensions = [];
        foreach ([
            'punctuality' => 'punctuality_score',
            'quality' => 'quality_score',
            'communication' => 'communication_score',
            'value' => 'value_score',
        ] as $key => $col) {
            $vals = $rows
                ->pluck($col)
                ->filter(fn ($v) => $v !== null && $v >= 1 && $v <= 5)
                ->map(fn ($v) => (int) $v);

            if ($vals->count() > 0) {
                $dimensions[$key] = [
                    'avg' => round((float) $vals->avg(), 2),
                    'count' => $vals->count(),
                ];
            }
        }

        $profile->update([
            'rating_avg' => $avg,
            'rating_count' => $count,
            'rating_distribution' => $distribution,
            'rating_dimensions' => $dimensions ?: null,
            'rating_last_at' => $rows->max('published_at') ?? now(),
        ]);
    }
}
