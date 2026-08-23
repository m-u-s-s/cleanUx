<?php

namespace App\Services\Analytics;

use App\Models\Booking;
use App\Models\EnterpriseBookingApproval;
use App\Models\FinanceInvoice;
use App\Models\MissionIncident;
use App\Models\MissionQualityReview;
use App\Models\OrganizationSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Raw database queries that fetch data for KPI calculations. */
class KpiAggregator
{
    // ──────────────────────────────────────────────────────
    // Booking aggregates
    // ──────────────────────────────────────────────────────

    public function totalBookingsBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->applyDateRange($this->scopedQuery($orgId), $from, $to)->count();
    }

    public function revenueBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) $this->applyDateRange($this->scopedQuery($orgId), $from, $to)
            ->whereNotIn('status', $this->cancelledStatuses())
            ->sum('estimated_price');
    }

    public function cancelledCountBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->applyDateRange($this->scopedQuery($orgId), $from, $to)
            ->whereIn('status', $this->cancelledStatuses())
            ->count();
    }

    public function completedCountBetween(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->applyDateRange($this->scopedQuery($orgId), $from, $to)
            ->whereIn('status', $this->completedStatuses())
            ->count();
    }

    // ──────────────────────────────────────────────────────
    // Time series
    // ──────────────────────────────────────────────────────

    public function monthlyRevenueRows(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', scheduled_date)"
            : "DATE_FORMAT(scheduled_date, '%Y-%m')";

        $col = $this->bookingDateColumn();
        $q = $this->scopedQuery($orgId)
            ->selectRaw("{$monthExpr} as ym")
            ->selectRaw('COALESCE(SUM(estimated_price), 0) as revenue')
            ->selectRaw('COUNT(*) as bookings_count')
            ->whereNotIn('status', $this->cancelledStatuses())
            ->groupBy('ym')
            ->orderBy('ym');

        $this->applyDateRange($q, $from, $to);

        return $q->get()->keyBy('ym');
    }

    public function statusBreakdownRows(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $query = Booking::query();
        $this->applyPeriod($query, $from, $to);
        $this->applyOrganizationScope($query, $orgId);

        return $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();
    }

    public function topServicesRows(?int $orgId, CarbonImmutable $from, CarbonImmutable $to, int $limit): Collection
    {
        $query = Booking::query();
        $this->applyPeriod($query, $from, $to);
        $this->applyOrganizationScope($query, $orgId);

        $table = $this->bookingTable();

        if (Schema::hasColumn($table, 'service_catalog_id')) {
            $query->leftJoin('service_catalogs', 'service_catalogs.id', '=', $table.'.service_catalog_id');

            return $query
                ->selectRaw('COALESCE(service_catalogs.name, "Service") as service_name, COUNT(*) as count')
                ->groupBy('service_name')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();
        }

        foreach (['type_service', 'service_type', 'service_name'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $query
                    ->selectRaw($this->qualifyColumn($column).' as service_name, COUNT(*) as count')
                    ->groupBy($this->qualifyColumn($column))
                    ->orderByDesc('count')
                    ->limit($limit)
                    ->get();
            }
        }

        return collect();
    }

    public function topSitesRows(int $orgId, CarbonImmutable $from, CarbonImmutable $to, int $limit): Collection
    {
        $col = $this->bookingDateColumn();
        $q = Booking::query()
            ->where('customer_organization_id', $orgId)
            ->whereNotNull('organization_site_id')
            ->join('organization_sites', 'organization_sites.id', '=', 'bookings.organization_site_id')
            ->selectRaw('bookings.organization_site_id as site_id')
            ->selectRaw('organization_sites.name as site_name')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(bookings.estimated_price), 0) as revenue')
            ->whereNotIn('bookings.status', $this->cancelledStatuses())
            ->groupBy('bookings.organization_site_id', 'organization_sites.name')
            ->orderByDesc('count')
            ->limit($limit);

        // Use whereDate for plain date columns to handle datetime storage correctly.
        $q->whereDate('bookings.'.$col, '>=', $from->toDateString())
            ->whereDate('bookings.'.$col, '<=', $to->toDateString());

        return $q->get();
    }

    public function satisfactionTrendRows(?int $orgId, CarbonImmutable $from): Collection
    {
        if (! class_exists(MissionQualityReview::class)) {
            return collect();
        }

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

        if ($orgId) {
            $query->join('missions', 'missions.id', '=', 'mission_quality_reviews.mission_id')
                ->where('missions.organization_account_id', $orgId);
        }

        return $query->groupBy('ym')->orderBy('ym')->get()->keyBy('ym');
    }

    public function averageRatingStats(?int $orgId, CarbonImmutable $from, CarbonImmutable $to): ?object
    {
        if (! class_exists(MissionQualityReview::class)) {
            return null;
        }

        $query = MissionQualityReview::query()
            ->whereNotNull('overall_rating')
            ->whereBetween('mission_quality_reviews.created_at', [$from, $to]);

        if ($orgId) {
            $query->join('missions', 'missions.id', '=', 'mission_quality_reviews.mission_id')
                ->where('missions.organization_account_id', $orgId);
        }

        return $query->selectRaw('AVG(overall_rating) as avg, COUNT(*) as cnt')->first();
    }

    public function activeSiteStats(int $orgId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $total = OrganizationSite::query()
            ->where('organization_account_id', $orgId)
            ->count();

        $active = Booking::query()
            ->where('customer_organization_id', $orgId)
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->distinct('organization_site_id')
            ->whereNotNull('organization_site_id')
            ->count('organization_site_id');

        return ['total' => $total, 'active' => $active];
    }

    public function alertStats(?int $orgId): array
    {
        $invoiceQuery = FinanceInvoice::query()
            ->whereNull('paid_at')
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', now());

        if ($orgId) {
            $invoiceQuery->where('organization_account_id', $orgId);
        }

        $approvalsCount = 0;
        if (class_exists(EnterpriseBookingApproval::class)) {
            $q = EnterpriseBookingApproval::query()->where('status', 'pending');
            if ($orgId) {
                $q->where('organization_account_id', $orgId);
            }
            $approvalsCount = $q->count();
        }

        $incidentsCount = 0;
        if (class_exists(MissionIncident::class)) {
            $q = MissionIncident::query()->where('status', 'open');
            if ($orgId) {
                $q->whereHas('mission', fn ($q2) => $q2->where('organization_account_id', $orgId));
            }
            $incidentsCount = $q->count();
        }

        $atRisk = $this->scopedQuery($orgId)
            ->whereIn('status', ['en_attente', 'pending', 'pending_approval'])
            ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays(2)->toDateString()])
            ->count();

        return [
            'overdue_invoices' => $invoiceQuery->count(),
            'pending_approvals' => $approvalsCount,
            'open_incidents' => $incidentsCount,
            'bookings_at_risk' => $atRisk,
        ];
    }

    // ──────────────────────────────────────────────────────
    // Query helpers
    // ──────────────────────────────────────────────────────

    public function scopedQuery(?int $orgId): Builder
    {
        $q = Booking::query();
        if ($orgId !== null) {
            $q->where('customer_organization_id', $orgId);
        }

        return $q;
    }

    public function bookingTable(): string
    {
        return (new Booking)->getTable();
    }

    public function bookingDateColumn(): string
    {
        $table = $this->bookingTable();
        foreach (['scheduled_date', 'date', 'rdv_date', 'jour', 'scheduled_at', 'created_at'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return 'created_at';
    }

    public function bookingAmountColumn(): ?string
    {
        $table = $this->bookingTable();
        foreach (['estimated_price', 'total_amount', 'montant_total', 'prix_total', 'amount', 'montant', 'prix', 'price', 'subtotal'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    public function qualifyColumn(string $column): string
    {
        return $this->bookingTable().'.'.$column;
    }

    public function cancelledStatuses(): array
    {
        return ['annule', 'annulé', 'cancelled', 'canceled', 'refuse', 'refusé'];
    }

    public function completedStatuses(): array
    {
        return ['termine', 'terminé', 'completed', 'done'];
    }

    /** Apply a date-range filter to a query, respecting date vs datetime columns. */
    private function applyDateRange(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $column = $this->bookingDateColumn();
        $qualified = $this->qualifyColumn($column);

        if (in_array($column, ['scheduled_at', 'created_at', 'updated_at'], true)) {
            return $query->whereBetween($qualified, [$from, $to]);
        }

        return $query
            ->whereDate($qualified, '>=', $from->toDateString())
            ->whereDate($qualified, '<=', $to->toDateString());
    }

    private function applyPeriod(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $column = $this->bookingDateColumn();
        $qualified = $this->qualifyColumn($column);

        if (in_array($column, ['scheduled_at', 'created_at', 'updated_at'], true)) {
            $query->whereBetween($qualified, [$from, $to]);

            return;
        }

        $query->whereDate($qualified, '>=', $from->toDateString())
            ->whereDate($qualified, '<=', $to->toDateString());
    }

    private function applyOrganizationScope(Builder $query, ?int $orgId): void
    {
        if ($orgId === null) {
            return;
        }

        $table = $this->bookingTable();
        foreach (['customer_organization_id', 'organization_account_id', 'organisation_account_id', 'client_organization_id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($this->qualifyColumn($column), $orgId);

                return;
            }
        }
    }
}
