<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\Mission;
use App\Models\MissionQualityReview;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7 — Calcul centralisé des KPIs business pour le dashboard analytics.
 *
 * Périmètre adapté automatiquement :
 *   - User entreprise → KPIs scopés à organization_account_id
 *   - User admin      → KPIs globaux plateforme
 *
 * Cache 5 minutes par scope (compromis fraîcheur / charge DB).
 *
 * Toutes les méthodes renvoient des structures sérialisables (arrays/Collections).
 */
class AnalyticsKpiService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(
        protected DateRangeResolver $dateResolver,
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
        $baseQuery = Booking::query();

        $this->applyPeriod($baseQuery, $from, $to);
        $this->applyOrganizationScope($baseQuery, $organizationAccountId);

        $totalBookings = (clone $baseQuery)->count();

        $validQuery = clone $baseQuery;
        $validQuery->whereNotIn('status', $this->nonCancelledStatuses());

        $amountColumn = $this->bookingAmountColumn();

        $revenue = $amountColumn
            ? (float) (clone $validQuery)->sum($this->qualifyBookingColumn($amountColumn))
            : 0.0;

        $bookingsCount = (clone $validQuery)->count();

        $cancelledCount = (clone $baseQuery)
            ->whereIn('status', $this->nonCancelledStatuses())
            ->count();

        $completedCount = (clone $baseQuery)
            ->whereIn('status', $this->completedStatuses())
            ->count();

        return [
            'revenue' => [
                'value' => round($revenue, 2),
            ],
            'bookings_count' => [
                'value' => $bookingsCount,
            ],
            'cancellation_rate' => [
                'value' => $totalBookings > 0
                    ? round(($cancelledCount / $totalBookings) * 100, 2)
                    : 0.0,
            ],
            'completed_count' => [
                'value' => $completedCount,
                'completion_rate' => $totalBookings > 0
                    ? round(($completedCount / $totalBookings) * 100, 2)
                    : 0.0,
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

            $driver = DB::connection()->getDriverName();
            $monthExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', scheduled_date)"
                : "DATE_FORMAT(scheduled_date, '%Y-%m')";

            $rows = $this->scopedBookingQuery($organizationAccountId)
                ->selectRaw("{$monthExpr} as ym")
                ->selectRaw("COALESCE(SUM(estimated_price), 0) as revenue")
                ->selectRaw("COUNT(*) as bookings_count")
                ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('status', ['annule', 'cancelled', 'refuse'])
                ->groupBy('ym')
                ->orderBy('ym')
                ->get()
                ->keyBy('ym');

            // Remplir les mois manquants avec zéro pour avoir une série continue
            $series = collect();
            $cursor = $from;
            while ($cursor->lessThanOrEqualTo($to)) {
                $ym = $cursor->format('Y-m');
                $row = $rows->get($ym);

                $series->push([
                    'month'          => $ym,
                    'label'          => $cursor->locale('fr')->isoFormat('MMM YYYY'),
                    'revenue'        => $row ? (float) $row->revenue : 0.0,
                    'bookings_count' => $row ? (int) $row->bookings_count : 0,
                ]);

                $cursor = $cursor->addMonth();
            }

            return $series;
        });
    }

    /**
     * Répartition par statut sur la période (pour donut chart).
     *
     * @return Collection<int, array{status:string, label:string, count:int, color:string}>
     */
    public function statusBreakdown(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $query = Booking::query();

        $this->applyPeriod($query, $from, $to);
        $this->applyOrganizationScope($query, $organizationAccountId);

        return $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * Top services (pour bar chart horizontal).
     *
     * @return Collection<int, array{service_id:int, service_name:string, count:int, revenue:float}>
     */
    public function topServices(?int $organizationAccountId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): Collection
    {
        $query = Booking::query();

        $this->applyPeriod($query, $from, $to);
        $this->applyOrganizationScope($query, $organizationAccountId);

        $table = $this->bookingTable();

        if (Schema::hasColumn($table, 'service_catalog_id')) {
            $query->leftJoin('service_catalogs', 'service_catalogs.id', '=', $table . '.service_catalog_id');

            return $query
                ->selectRaw('COALESCE(service_catalogs.name, "Service") as service_name, COUNT(*) as count')
                ->groupBy('service_name')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn($row) => [
                    'service_name' => (string) $row->service_name,
                    'count' => (int) $row->count,
                ]);
        }

        foreach (['type_service', 'service_type', 'service_name'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $query
                    ->selectRaw($this->qualifyBookingColumn($column) . ' as service_name, COUNT(*) as count')
                    ->groupBy($this->qualifyBookingColumn($column))
                    ->orderByDesc('count')
                    ->limit($limit)
                    ->get()
                    ->map(fn($row) => [
                        'service_name' => (string) $row->service_name,
                        'count' => (int) $row->count,
                    ]);
            }
        }

        return collect();
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
            return Booking::query()
                ->where('customer_organization_id', $organizationAccountId)
                ->whereNotNull('organization_site_id')
                ->join('organization_sites', 'organization_sites.id', '=', 'bookings.organization_site_id')
                ->selectRaw('bookings.organization_site_id as site_id')
                ->selectRaw('organization_sites.name as site_name')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('COALESCE(SUM(bookings.estimated_price), 0) as revenue')
                ->whereBetween('bookings.scheduled_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('bookings.status', ['annule', 'cancelled', 'refuse'])
                ->groupBy('bookings.organization_site_id', 'organization_sites.name')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'site_id'   => (int) $r->site_id,
                    'site_name' => (string) $r->site_name,
                    'count'     => (int) $r->count,
                    'revenue'   => (float) $r->revenue,
                ]);
        });
    }

    /**
     * Évolution de la satisfaction (rating moyen par mois).
     */
    public function satisfactionTrend(?int $organizationAccountId, int $months = 12): Collection
    {
        if (! class_exists(MissionQualityReview::class)) {
            return collect();
        }

        $cacheKey = $this->cacheKey('satisfaction_trend', $organizationAccountId, null, null, ['m' => $months]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($organizationAccountId, $months) {
            $from = CarbonImmutable::now()->subMonths($months - 1)->startOfMonth();

            $driver = DB::connection()->getDriverName();
            $monthExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', mission_quality_reviews.created_at)"
                : "DATE_FORMAT(mission_quality_reviews.created_at, '%Y-%m')";

            $query = MissionQualityReview::query()
                ->selectRaw("{$monthExpr} as ym")
                ->selectRaw('AVG(overall_rating) as avg_rating')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('overall_rating')
                ->where('mission_quality_reviews.created_at', '>=', $from);

            if ($organizationAccountId) {
                $query->join('missions', 'missions.id', '=', 'mission_quality_reviews.mission_id')
                    ->where('missions.organization_account_id', $organizationAccountId);
            }

            $rows = $query->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');

            $series = collect();
            $cursor = $from;
            $now    = CarbonImmutable::now();
            while ($cursor->lessThanOrEqualTo($now)) {
                $ym  = $cursor->format('Y-m');
                $row = $rows->get($ym);

                $series->push([
                    'month'      => $ym,
                    'label'      => $cursor->locale('fr')->isoFormat('MMM YYYY'),
                    'avg_rating' => $row ? round((float) $row->avg_rating, 2) : null,
                    'count'      => $row ? (int) $row->count : 0,
                ]);

                $cursor = $cursor->addMonth();
            }

            return $series;
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
            $invoiceQuery = FinanceInvoice::query()
                ->whereNull('paid_at')
                ->whereNotNull('due_at')
                ->whereDate('due_at', '<', now());

            if ($organizationAccountId) {
                $invoiceQuery->where('organization_account_id', $organizationAccountId);
            }

            $approvalsCount = 0;
            if (class_exists(\App\Models\EnterpriseBookingApproval::class)) {
                $approvalQuery = \App\Models\EnterpriseBookingApproval::query()
                    ->where('status', 'pending');
                if ($organizationAccountId) {
                    $approvalQuery->where('organization_account_id', $organizationAccountId);
                }
                $approvalsCount = $approvalQuery->count();
            }

            $incidentsCount = 0;
            if (class_exists(\App\Models\MissionIncident::class)) {
                $incidentQuery = \App\Models\MissionIncident::query()
                    ->where('status', 'open');
                if ($organizationAccountId) {
                    $incidentQuery->whereHas('mission', fn($q) => $q->where('organization_account_id', $organizationAccountId));
                }
                $incidentsCount = $incidentQuery->count();
            }

            // Bookings à risque : J-1 ou J-2 mais toujours en attente d'approbation
            $atRiskQuery = $this->scopedBookingQuery($organizationAccountId)
                ->whereIn('status', ['en_attente', 'pending', 'pending_approval'])
                ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays(2)->toDateString()]);

            return [
                'overdue_invoices'  => $invoiceQuery->count(),
                'pending_approvals' => $approvalsCount,
                'open_incidents'    => $incidentsCount,
                'bookings_at_risk'  => $atRiskQuery->count(),
            ];
        });
    }

    public function flush(?int $organizationAccountId = null): void
    {
        // Invalidation simple : laisser expirer naturellement (5 min)
        // Pour invalidation immédiate, lister les clés et les effacer.
    }

    // ──────────────────────────────────────────────────────
    // KPI calculators (private)
    // ──────────────────────────────────────────────────────


    private function revenueKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $current  = $this->revenueBetween($orgId, $from, $to);
        $previous = $this->revenueBetween($orgId, $prevFrom, $prevTo);

        return [
            'value'    => $current,
            'currency' => 'EUR',
            'trend'    => $this->trend($previous, $current),
            'label'    => 'Chiffre d\'affaires',
        ];
    }

    private function bookingsCountKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $current  = $this->bookingsCountBetween($orgId, $from, $to);
        $previous = $this->bookingsCountBetween($orgId, $prevFrom, $prevTo);

        return [
            'value' => $current,
            'trend' => $this->trend((float) $previous, (float) $current),
            'label' => 'Rendez-vous',
        ];
    }

    private function completedKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $total = $this->bookingsCountBetween($orgId, $from, $to);
        $completed = $this->scopedBookingQuery($orgId)
            ->whereIn('status', ['termine', 'completed', 'done'])
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        return [
            'value'           => $completed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
            'label'           => 'Terminés',
        ];
    }

    private function cancellationRateKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo): array
    {
        $current  = $this->cancellationRateBetween($orgId, $from, $to);
        $previous = $this->cancellationRateBetween($orgId, $prevFrom, $prevTo);

        return [
            'value' => $current,
            'trend' => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : null,
            'label' => 'Taux d\'annulation',
        ];
    }

    private function averageRatingKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! class_exists(MissionQualityReview::class)) {
            return ['value' => null, 'count' => 0, 'label' => 'Satisfaction moyenne'];
        }

        $query = MissionQualityReview::query()
            ->whereNotNull('overall_rating')
            ->whereBetween('mission_quality_reviews.created_at', [$from, $to]);

        if ($orgId) {
            $query->join('missions', 'missions.id', '=', 'mission_quality_reviews.mission_id')
                ->where('missions.organization_account_id', $orgId);
        }

        $stats = $query->selectRaw('AVG(overall_rating) as avg, COUNT(*) as cnt')->first();

        return [
            'value' => $stats && $stats->avg ? round((float) $stats->avg, 2) : null,
            'count' => $stats ? (int) $stats->cnt : 0,
            'label' => 'Satisfaction moyenne',
        ];
    }

    private function activeSitesKpi(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $orgId) {
            return ['value' => 0, 'total' => 0, 'label' => 'Sites actifs'];
        }

        $totalSites = \App\Models\OrganizationSite::query()
            ->where('organization_account_id', $orgId)
            ->count();

        $activeSites = Booking::query()
            ->where('customer_organization_id', $orgId)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->distinct('organization_site_id')
            ->whereNotNull('organization_site_id')
            ->count('organization_site_id');

        return [
            'value' => $activeSites,
            'total' => $totalSites,
            'label' => 'Sites actifs',
        ];
    }

    // ──────────────────────────────────────────────────────
    // Helpers privés
    // ──────────────────────────────────────────────────────

    private function revenueBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) $this->scopedBookingQuery($orgId)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', ['annule', 'cancelled', 'refuse'])
            ->sum('estimated_price');
    }

    private function bookingsCountBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->scopedBookingQuery($orgId)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    private function cancellationRateBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $total = $this->bookingsCountBetween($orgId, $from, $to);
        if ($total === 0) return 0.0;

        $cancelled = $this->scopedBookingQuery($orgId)
            ->whereIn('status', ['annule', 'cancelled', 'refuse'])
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        return round(($cancelled / $total) * 100, 1);
    }

    private function scopedBookingQuery(?int $orgId)
    {
        $q = Booking::query();
        if ($orgId !== null) {
            $q->where('customer_organization_id', $orgId);
        }
        return $q;
    }

    private function trend(float $previous, float $current): ?float
    {
        if ($previous == 0.0) return null;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'en_attente', 'pending', 'pending_approval'   => 'En attente',
            'confirme', 'confirmed'                        => 'Confirmé',
            'en_route', 'on_route'                         => 'En route',
            'sur_place', 'on_site', 'in_progress'          => 'Sur place',
            'termine', 'completed', 'done'                 => 'Terminé',
            'annule', 'cancelled', 'refuse', 'refused'     => 'Annulé',
            default                                        => ucfirst($status),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'en_attente', 'pending', 'pending_approval'   => '#f59e0b',
            'confirme', 'confirmed'                        => '#3b82f6',
            'en_route', 'on_route'                         => '#8b5cf6',
            'sur_place', 'on_site', 'in_progress'          => '#06b6d4',
            'termine', 'completed', 'done'                 => '#10b981',
            'annule', 'cancelled', 'refuse', 'refused'     => '#ef4444',
            default                                        => '#64748b',
        };
    }

    private function cacheKey(string $type, ?int $orgId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, array $extra = []): string
    {
        $parts = [
            'analytics',
            $type,
            'org=' . ($orgId ?? 'global'),
        ];
        if ($from) $parts[] = 'from=' . $from->toDateString();
        if ($to)   $parts[] = 'to=' . $to->toDateString();
        foreach ($extra as $k => $v) {
            $parts[] = "{$k}={$v}";
        }
        return implode(':', $parts);
    }

    private function bookingTable(): string
    {
        return (new Booking())->getTable();
    }

    private function bookingDateColumn(): string
    {
        $table = $this->bookingTable();

        foreach (
            [
                'scheduled_date',
                'date',
                'rdv_date',
                'jour',
                'scheduled_at',
                'created_at',
            ] as $column
        ) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'created_at';
    }

    private function bookingAmountColumn(): ?string
    {
        $table = $this->bookingTable();

        foreach (
            [
                'estimated_price',
                'total_amount',
                'montant_total',
                'prix_total',
                'amount',
                'montant',
                'prix',
                'price',
                'subtotal',
            ] as $column
        ) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function qualifyBookingColumn(string $column): string
    {
        return $this->bookingTable() . '.' . $column;
    }

    private function applyPeriod(
        Builder $query,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): Builder {
        $column = $this->bookingDateColumn();
        $qualifiedColumn = $this->qualifyBookingColumn($column);

        if (in_array($column, ['scheduled_at', 'created_at', 'updated_at'], true)) {
            return $query->whereBetween($qualifiedColumn, [$from, $to]);
        }

        return $query
            ->whereDate($qualifiedColumn, '>=', $from->toDateString())
            ->whereDate($qualifiedColumn, '<=', $to->toDateString());
    }

    private function applyOrganizationScope(
        Builder $query,
        ?int $organizationAccountId
    ): Builder {
        if ($organizationAccountId === null) {
            return $query;
        }

        $table = $this->bookingTable();

        foreach (
            [
                'customer_organization_id',
                'organization_account_id',
                'organisation_account_id',
                'client_organization_id',
            ] as $column
        ) {
            if (Schema::hasColumn($table, $column)) {
                return $query->where($this->qualifyBookingColumn($column), $organizationAccountId);
            }
        }

        return $query;
    }

    private function nonCancelledStatuses(): array
    {
        return [
            'annule',
            'annulé',
            'cancelled',
            'canceled',
            'refuse',
            'refusé',
        ];
    }

    private function completedStatuses(): array
    {
        return [
            'termine',
            'terminé',
            'completed',
            'done',
        ];
    }
}
