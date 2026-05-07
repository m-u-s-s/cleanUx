<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;

/**
 * Phase 7 — Résolveur de presets de période pour les dashboards analytics.
 *
 * Presets supportés :
 *   - last_7d, last_30d, last_90d
 *   - this_month, last_month
 *   - this_quarter, last_quarter
 *   - this_year, last_year
 *   - ytd (year-to-date)
 *   - custom (avec from/to fournis)
 */
class DateRangeResolver
{
    public const PRESETS = [
        'last_7d',
        'last_30d',
        'last_90d',
        'this_month',
        'last_month',
        'this_quarter',
        'last_quarter',
        'ytd',
        'this_year',
        'last_year',
        'custom',
    ];

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}  [from, to, label]
     */
    public function resolve(string $preset, ?string $customFrom = null, ?string $customTo = null): array
    {
        $today = CarbonImmutable::today();

        return match ($preset) {
            'last_7d' => [
                $today->subDays(6)->startOfDay(),
                $today->endOfDay(),
                '7 derniers jours',
            ],
            'last_30d' => [
                $today->subDays(29)->startOfDay(),
                $today->endOfDay(),
                '30 derniers jours',
            ],
            'last_90d' => [
                $today->subDays(89)->startOfDay(),
                $today->endOfDay(),
                '90 derniers jours',
            ],
            'this_month' => [
                $today->startOfMonth(),
                $today->endOfMonth(),
                'Ce mois-ci',
            ],
            'last_month' => [
                $today->subMonth()->startOfMonth(),
                $today->subMonth()->endOfMonth(),
                'Mois précédent',
            ],
            'this_quarter' => [
                $today->startOfQuarter(),
                $today->endOfQuarter(),
                'Ce trimestre',
            ],
            'last_quarter' => [
                $today->subQuarter()->startOfQuarter(),
                $today->subQuarter()->endOfQuarter(),
                'Trimestre précédent',
            ],
            'ytd' => [
                $today->startOfYear(),
                $today->endOfDay(),
                'Année en cours',
            ],
            'this_year' => [
                $today->startOfYear(),
                $today->endOfYear(),
                'Année courante',
            ],
            'last_year' => [
                $today->subYear()->startOfYear(),
                $today->subYear()->endOfYear(),
                'Année précédente',
            ],
            'custom' => $this->resolveCustom($customFrom, $customTo),
            default  => $this->resolve('last_30d'),
        };
    }

    public function presetOptions(): array
    {
        return [
            ['value' => 'last_7d',      'label' => '7 derniers jours'],
            ['value' => 'last_30d',     'label' => '30 derniers jours'],
            ['value' => 'last_90d',     'label' => '90 derniers jours'],
            ['value' => 'this_month',   'label' => 'Ce mois'],
            ['value' => 'last_month',   'label' => 'Mois précédent'],
            ['value' => 'this_quarter', 'label' => 'Ce trimestre'],
            ['value' => 'last_quarter', 'label' => 'Trimestre précédent'],
            ['value' => 'ytd',          'label' => 'Année en cours'],
            ['value' => 'last_year',    'label' => 'Année précédente'],
            ['value' => 'custom',       'label' => 'Personnalisé'],
        ];
    }

    private function resolveCustom(?string $customFrom, ?string $customTo): array
    {
        if (! $customFrom || ! $customTo) {
            return $this->resolve('last_30d');
        }

        try {
            $from = CarbonImmutable::parse($customFrom)->startOfDay();
            $to   = CarbonImmutable::parse($customTo)->endOfDay();

            // Sécurité : ne pas autoriser une période > 5 ans
            if ($from->diffInYears($to) > 5) {
                return $this->resolve('last_year');
            }

            // Sécurité : si from > to, swap
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            return [
                $from,
                $to,
                $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
            ];
        } catch (\Throwable $e) {
            return $this->resolve('last_30d');
        }
    }
}
