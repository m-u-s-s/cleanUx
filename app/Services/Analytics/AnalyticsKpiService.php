<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 7 — Calcul centralisé des KPIs business pour le dashboard analytics.
 *
 * Thin orchestrator: delegates to KpiAggregator (DB queries),
 * KpiCalculator (pure math), and KpiFormatter (output shaping).
 *
 * Périmètre adapté automatiquement :
 *   - User entreprise → KPIs scopés à organization_account_id
 *   - User admin      → KPIs globaux plateforme
 *
 * Cache 5 minutes par scope (compromis fraîcheur / charge DB).
 */
class AnalyticsKpiService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        protected DateRangeResolver $dateResolver,
        protected KpiAggregator $aggregator,
        protected KpiCalculator $calculator,
        protected KpiFormatter $formatter,
    ) {}

    // ──────────────────────────────────────────────────────
    // KPIs principaux pour cards
    // ──────────────────────────────────────────────────────

    /**
     * Compute les KPIs principaux pour une période donnée.
     *
     * @return array{
     *   revenue: array{value:float, currency:string, trend:?float, label:string},
     *   bookings_count: array{value:int, trend:?float, label:string},
     *   completed_count: array{value:int, completion_rate:?float, label:string},
     *   cancellation_rate: array{value:float, trend:?float, label:string},
     *   average_rating: array{value:?float, count:int, label:string},
     *   active_sites: array{value:int, total:int, label:string},
     * }
     */
    public function mainKpis(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orgId = $organizationAccountId;

        $total     = $this->aggregator->totalBookingsBetween($orgId, $from, $to);
        $cancelled = $this->aggregator->cancelledCountBetween($orgId, $from, $to);
        $completed = $this->aggregator->completedCountBetween($orgId, $from, $to);
        $revenue   = $this->aggregator->revenueBetween($orgId, $from, $to);

        // Derive non-cancelled count from total and cancelled
        $validCount = $total - $cancelled;

        return [
            'revenue' => [
                'value' => round($revenue, 2),
            ],
            'bookings_count' => [
                'value' => $validCount,
            ],
            'cancellation_rate' => [
                'value' => $this->calculator->cancellationRate($total, $cancelled),
            ],
            'completed_count' => [
                'value'           => $completed,
                'completion_rate' => $this->calculator->completionRate($total, $completed),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────
    // Séries temporelles pour graphiques
    // ──────────────────────────────────────────────────────

    /**
     * Revenu mensuel sur N mois.
     *
     * @return Collection<int, array{label:string, month:string, revenue:float, bookings_count:int}>
     */
    public function monthlyRevenue(?int $organizationAccountId, int $months = 12): Collection
    {
        $cacheKey = $this->cacheKey('monthly_revenue', $organizationAccountId, null, null, ['m' => $months]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationAccountId, $months) {
            $from = CarbonImmutable::now()->subMonths($months - 1)->startOfMonth();
            $to   = CarbonImmutable::now()->endOfMonth();

            $rows = $this->aggregator->monthlyRevenueRows($organizationAccountId, $from, $to);

            return $this->formatter->fillMonthlyRevenueSeries($rows, $from, $to);
        });
    }

    /**
     * Répartition par statut sur la période (pour donut chart).
     *
     * @return Collection<int, array{status:string, label:string, count:int, color:string}>
     */
    public function statusBreakdown(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $rows = $this->aggregator->statusBreakdownRows($organizationAccountId, $from, $to);

        return $this->formatter->formatStatusBreakdown($rows);
    }

    /**
     * Top services (pour bar chart horizontal).
     *
     * @return Collection<int, array{service_id:int, service_name:string, count:int, revenue:float}>
     */
    public function topServices(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        $rows = $this->aggregator->topServicesRows($organizationAccountId, $from, $to, $limit);

        return $this->formatter->formatTopServices($rows);
    }

    /**
     * Top sites (pour client entreprise multi-sites).
     */
    public function topSites(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        if (! $organizationAccountId) {
            return collect();
        }

        $cacheKey = $this->cacheKey('top_sites', $organizationAccountId, $from, $to, ['l' => $limit]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationAccountId, $from, $to, $limit) {
            $rows = $this->aggregator->topSitesRows($organizationAccountId, $from, $to, $limit);

            return $this->formatter->formatTopSites($rows);
        });
    }

    /**
     * Évolution de la satisfaction (rating moyen par mois).
     */
    public function satisfactionTrend(?int $organizationAccountId, int $months = 12): Collection
    {
        $cacheKey = $this->cacheKey('satisfaction_trend', $organizationAccountId, null, null, ['m' => $months]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationAccountId, $months) {
            $from = CarbonImmutable::now()->subMonths($months - 1)->startOfMonth();
            $to   = CarbonImmutable::now();

            $rows = $this->aggregator->satisfactionTrendRows($organizationAccountId, $from);

            return $this->formatter->fillSatisfactionSeries($rows, $from, $to);
        });
    }

    /**
     * Alertes business : éléments qui méritent l'attention immédiate.
     *
     * @return array{
     *   overdue_invoices: int,
     *   pending_approvals: int,
     *   open_incidents: int,
     *   bookings_at_risk: int,
     * }
     */
    public function alerts(?int $organizationAccountId): array
    {
        $cacheKey = $this->cacheKey('alerts', $organizationAccountId);

        return Cache::remember($cacheKey, 60, function () use ($organizationAccountId) {
            return $this->aggregator->alertStats($organizationAccountId);
        });
    }

    public function flush(?int $organizationAccountId = null): void
    {
        // Invalidation simple : laisser expirer naturellement (5 min).
    }

    // ──────────────────────────────────────────────────────
    // Cache key builder
    // ──────────────────────────────────────────────────────

    private function cacheKey(
        string $type,
        ?int $orgId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        array $extra = []
    ): string {
        $parts = ['analytics', $type, 'org=' . ($orgId ?? 'global')];
        if ($from) {
            $parts[] = 'from=' . $from->toDateString();
        }
        if ($to) {
            $parts[] = 'to=' . $to->toDateString();
        }
        foreach ($extra as $k => $v) {
            $parts[] = "{$k}={$v}";
        }
        return implode(':', $parts);
    }
}
