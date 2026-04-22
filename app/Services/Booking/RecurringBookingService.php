<?php

namespace App\Services\Booking;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RecurringBookingService
{
    public function normalizeSettings(array $data, ?string $fallbackDate = null): array
    {
        $legacyRule = Arr::get($data, 'recurrence_rule');
        $frequency = Arr::get($data, 'recurrence_frequency');
        $interval = (int) Arr::get($data, 'recurrence_interval', 1);

        if (! $frequency && $legacyRule) {
            [$frequency, $interval] = match ($legacyRule) {
                'biweekly' => ['weekly', 2],
                'monthly' => ['monthly', 1],
                default => ['weekly', 1],
            };
        }

        $days = $this->normalizeDays(Arr::get($data, 'recurrence_days'), $fallbackDate);
        $until = Arr::get($data, 'recurrence_until') ? Carbon::parse(Arr::get($data, 'recurrence_until'))->startOfDay() : null;
        $count = Arr::get($data, 'recurrence_count');
        $count = filled($count) ? (int) $count : null;

        return [
            'frequency' => $frequency,
            'interval' => max(1, min(12, $interval)),
            'until' => $until,
            'count' => $count,
            'days' => $days,
        ];
    }

    public function validateSettings(array $settings, string $startDate): void
    {
        $frequency = $settings['frequency'] ?? null;

        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            throw ValidationException::withMessages([
                'recurrence_frequency' => 'Choisissez une fréquence de récurrence valide.',
            ]);
        }

        if (empty($settings['until']) && empty($settings['count'])) {
            throw ValidationException::withMessages([
                'recurrence_count' => 'Indiquez une date de fin ou un nombre d’occurrences.',
            ]);
        }

        if (! empty($settings['count']) && (int) $settings['count'] > 52) {
            throw ValidationException::withMessages([
                'recurrence_count' => 'La série est limitée à 52 occurrences maximum.',
            ]);
        }

        if (! empty($settings['until'])) {
            $start = Carbon::parse($startDate)->startOfDay();
            if ($settings['until']->lt($start)) {
                throw ValidationException::withMessages([
                    'recurrence_until' => 'La date de fin doit être postérieure ou égale à la première intervention.',
                ]);
            }

            if ($settings['until']->gt($start->copy()->addMonths(12))) {
                throw ValidationException::withMessages([
                    'recurrence_until' => 'La série est limitée à 12 mois pour le moment.',
                ]);
            }
        }
    }

    public function generateOccurrences(string $date, string $heure, array $settings): array
    {
        $start = Carbon::parse($date.' '.$heure);
        $frequency = $settings['frequency'];
        $interval = (int) ($settings['interval'] ?? 1);
        $until = $settings['until'] ?? null;
        $count = $settings['count'] ?? null;
        $maxOccurrences = $count ?: 52;
        $occurrences = [];

        if ($frequency === 'monthly') {
            $cursor = $start->copy();
            while (count($occurrences) < $maxOccurrences) {
                if ($until && $cursor->copy()->startOfDay()->gt($until)) {
                    break;
                }

                $occurrences[] = [
                    'date' => $cursor->toDateString(),
                    'heure' => $cursor->format('H:i'),
                ];

                $cursor->addMonthsNoOverflow($interval);
            }

            return $occurrences;
        }

        if ($frequency === 'daily') {
            $cursor = $start->copy();
            while (count($occurrences) < $maxOccurrences) {
                if ($until && $cursor->copy()->startOfDay()->gt($until)) {
                    break;
                }

                $occurrences[] = [
                    'date' => $cursor->toDateString(),
                    'heure' => $cursor->format('H:i'),
                ];

                $cursor->addDays($interval);
            }

            return $occurrences;
        }

        $days = $settings['days'] ?: [$start->isoWeekday()];
        $cursor = $start->copy()->startOfDay();
        $anchorWeek = $start->copy()->startOfWeek(Carbon::MONDAY);

        while (count($occurrences) < $maxOccurrences) {
            if ($until && $cursor->gt($until)) {
                break;
            }

            $weekDiff = $anchorWeek->diffInWeeks($cursor->copy()->startOfWeek(Carbon::MONDAY));
            $matchesWeek = ($weekDiff % $interval) === 0;
            $matchesDay = in_array($cursor->isoWeekday(), $days, true);
            $isAfterStart = $cursor->greaterThanOrEqualTo($start->copy()->startOfDay());

            if ($matchesWeek && $matchesDay && $isAfterStart) {
                $occurrences[] = [
                    'date' => $cursor->toDateString(),
                    'heure' => $start->format('H:i'),
                ];
            }

            $cursor->addDay();
        }

        return $occurrences;
    }

    public function normalizeDays(null|array|string $days, ?string $fallbackDate = null): array
    {
        $normalized = collect(Arr::wrap($days))
            ->map(function ($day) {
                if (is_numeric($day)) {
                    return max(1, min(7, (int) $day));
                }

                return match ((string) $day) {
                    'mon' => 1,
                    'tue' => 2,
                    'wed' => 3,
                    'thu' => 4,
                    'fri' => 5,
                    'sat' => 6,
                    'sun' => 7,
                    default => null,
                };
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        if ($fallbackDate) {
            return [Carbon::parse($fallbackDate)->isoWeekday()];
        }

        return [];
    }

    public function toLegacyRule(array $settings): ?string
    {
        return match ($settings['frequency'] ?? null) {
            'weekly' => ((int) ($settings['interval'] ?? 1) === 2) ? 'biweekly' : 'weekly',
            'monthly' => 'monthly',
            'daily' => 'daily',
            default => null,
        };
    }
}
