<?php

namespace App\Services\Analytics;

use App\Support\International\Devise;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Formats KPI results for API / dashboard output.
 * Handles labels, currency, trend indicators, and series gap-filling.
 * No DB queries and no calculation logic here.
 */
class KpiFormatter
{
    public function statusLabel(string $status): string
    {
        return match ($status) {
            'en_attente', 'pending', 'pending_approval' => 'En attente',
            'confirme', 'confirmed' => 'Confirmé',
            'en_route', 'on_route' => 'En route',
            'sur_place', 'on_site', 'in_progress' => 'Sur place',
            'termine', 'completed', 'done' => 'Terminé',
            'annule', 'cancelled', 'refuse', 'refused' => 'Annulé',
            default => ucfirst($status),
        };
    }

    public function statusColor(string $status): string
    {
        return match ($status) {
            'en_attente', 'pending', 'pending_approval' => '#f59e0b',
            'confirme', 'confirmed' => '#3b82f6',
            'en_route', 'on_route' => '#8b5cf6',
            'sur_place', 'on_site', 'in_progress' => '#06b6d4',
            'termine', 'completed', 'done' => '#10b981',
            'annule', 'cancelled', 'refuse', 'refused' => '#ef4444',
            default => '#64748b',
        };
    }

    public function revenueKpi(float $current, float $previous, ?float $trend): array
    {
        return [
            'value' => round($current, 2),
            'currency' => Devise::plateforme(),
            'trend' => $trend,
            'label' => "Chiffre d'affaires",
        ];
    }

    public function bookingsCountKpi(int $current, ?float $trend): array
    {
        return [
            'value' => $current,
            'trend' => $trend,
            'label' => 'Rendez-vous',
        ];
    }

    public function completedKpi(int $completed, ?float $completionRate): array
    {
        return [
            'value' => $completed,
            'completion_rate' => $completionRate,
            'label' => 'Terminés',
        ];
    }

    public function cancellationRateKpi(float $rate, ?float $trend): array
    {
        return [
            'value' => $rate,
            'trend' => $trend,
            'label' => "Taux d'annulation",
        ];
    }

    public function averageRatingKpi(?float $avg, int $count): array
    {
        return [
            'value' => $avg,
            'count' => $count,
            'label' => 'Satisfaction moyenne',
        ];
    }

    public function activeSitesKpi(int $active, int $total): array
    {
        return [
            'value' => $active,
            'total' => $total,
            'label' => 'Sites actifs',
        ];
    }

    /**
     * Fill in months with zero values to produce a continuous series.
     *
     * @param  Collection  $rows  Keyed by 'Y-m'
     * @return Collection<int, array{month:string, label:string, revenue:float, bookings_count:int}>
     */
    public function fillMonthlyRevenueSeries(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $series = collect();
        $cursor = $from;

        while ($cursor->lessThanOrEqualTo($to)) {
            $ym = $cursor->format('Y-m');
            $row = $rows->get($ym);

            $series->push([
                'month' => $ym,
                'label' => $cursor->locale('fr')->isoFormat('MMM YYYY'),
                'revenue' => $row ? (float) $row->revenue : 0.0,
                'bookings_count' => $row ? (int) $row->bookings_count : 0,
            ]);

            $cursor = $cursor->addMonth();
        }

        return $series;
    }

    /**
     * Fill in months with null values where no review data exists.
     *
     * @param  Collection  $rows  Keyed by 'Y-m'
     * @return Collection<int, array{month:string, label:string, avg_rating:?float, count:int}>
     */
    public function fillSatisfactionSeries(Collection $rows, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $series = collect();
        $cursor = $from;

        while ($cursor->lessThanOrEqualTo($to)) {
            $ym = $cursor->format('Y-m');
            $row = $rows->get($ym);

            $series->push([
                'month' => $ym,
                'label' => $cursor->locale('fr')->isoFormat('MMM YYYY'),
                'avg_rating' => $row ? round((float) $row->avg_rating, 2) : null,
                'count' => $row ? (int) $row->count : 0,
            ]);

            $cursor = $cursor->addMonth();
        }

        return $series;
    }

    public function formatStatusBreakdown(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'status' => $row->status,
            'count' => (int) $row->count,
        ]);
    }

    public function formatTopServices(Collection $rows): Collection
    {
        return $rows->map(fn ($row) => [
            'service_name' => (string) $row->service_name,
            'count' => (int) $row->count,
            // La blade lit toujours 'revenue' ; l'agrégat ne le calcule pas
            // (group by service_name sans SUM) → défaut 0.0 si absent.
            'revenue' => isset($row->revenue) ? (float) $row->revenue : 0.0,
        ]);
    }

    public function formatTopSites(Collection $rows): Collection
    {
        return $rows->map(fn ($r) => [
            'site_id' => (int) $r->site_id,
            'site_name' => (string) $r->site_name,
            'count' => (int) $r->count,
            'revenue' => (float) $r->revenue,
        ]);
    }
}
