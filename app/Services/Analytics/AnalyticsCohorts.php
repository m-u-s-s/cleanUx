<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cohort retention analyzer.
 *
 * Groupe les users par semaine d'apparition (cohort start = première semaine
 * où on observe `$entryEvent`), puis mesure pour chaque semaine N suivante
 * combien de ces users ont déclenché `$returnEvent`.
 *
 * Usage :
 *   AnalyticsCohorts::weekly(
 *     from: now()->subWeeks(8),
 *     to: now(),
 *     entryEvent: 'user.registered',
 *     returnEvent: 'booking.created',
 *     maxWeeks: 6
 *   );
 */
class AnalyticsCohorts
{
    /**
     * @return array<int, array{cohort_week: string, cohort_size: int, retention: array<int,int>}>
     */
    public static function weekly(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        string $entryEvent,
        string $returnEvent,
        int $maxWeeks = 6,
    ): array {
        $from = Carbon::instance($from)->startOfWeek();
        $to = Carbon::instance($to);

        $entryRows = AnalyticsEvent::query()
            ->named($entryEvent)
            ->between($from, $to)
            ->whereNotNull('user_id')
            ->get(['user_id', 'occurred_at']);

        // user_id => first cohort week start (Y-W)
        $userCohort = [];
        foreach ($entryRows as $row) {
            $uid = (int) $row->user_id;
            $weekStart = $row->occurred_at->copy()->startOfWeek()->format('Y-m-d');
            if (! isset($userCohort[$uid]) || $weekStart < $userCohort[$uid]) {
                $userCohort[$uid] = $weekStart;
            }
        }

        if (empty($userCohort)) {
            return [];
        }

        // Group users by cohort week
        $cohorts = [];
        foreach ($userCohort as $uid => $weekStart) {
            $cohorts[$weekStart][] = $uid;
        }
        ksort($cohorts);

        // For each cohort, compute retention[0..maxWeeks]
        $output = [];
        foreach ($cohorts as $weekStart => $userIds) {
            $cohortStart = Carbon::parse($weekStart);
            $retention = array_fill(0, $maxWeeks + 1, 0);

            // Fetch return events for these users within the window
            $returnRows = AnalyticsEvent::query()
                ->named($returnEvent)
                ->whereIn('user_id', $userIds)
                ->where('occurred_at', '>=', $cohortStart)
                ->where('occurred_at', '<', $cohortStart->copy()->addWeeks($maxWeeks + 1))
                ->get(['user_id', 'occurred_at']);

            $seenPerWeek = [];  // weekIndex => set<user_id>
            foreach ($returnRows as $row) {
                $weeksSince = (int) floor($cohortStart->diffInDays($row->occurred_at) / 7);
                if ($weeksSince < 0 || $weeksSince > $maxWeeks) {
                    continue;
                }
                $seenPerWeek[$weeksSince][(int) $row->user_id] = true;
            }

            for ($w = 0; $w <= $maxWeeks; $w++) {
                $retention[$w] = isset($seenPerWeek[$w]) ? count($seenPerWeek[$w]) : 0;
            }

            $output[] = [
                'cohort_week' => $weekStart,
                'cohort_size' => count($userIds),
                'retention' => $retention,
            ];
        }

        return $output;
    }
}
