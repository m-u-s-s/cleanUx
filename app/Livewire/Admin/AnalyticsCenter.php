<?php

namespace App\Livewire\Admin;

use App\Models\FinanceInvoice;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Analytics\AdminAnalyticsSnapshotService;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class AnalyticsCenter extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $status = '';
    public string $zoneId = '';
    public string $serviceId = '';
    public string $employeeId = '';
    public string $market = '';

    protected $queryString = [
        'search', 'dateFrom', 'dateTo', 'status', 'zoneId', 'serviceId', 'employeeId', 'market', 'page',
    ];

    public function mount(): void
    {
        if (blank($this->dateFrom)) {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }

        if (blank($this->dateTo)) {
            $this->dateTo = now()->endOfMonth()->toDateString();
        }
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingZoneId(): void { $this->resetPage(); }
    public function updatingServiceId(): void { $this->resetPage(); }
    public function updatingEmployeeId(): void { $this->resetPage(); }
    public function updatingMarket(): void { $this->resetPage(); }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('name')->get();
    }

    public function getServicesProperty()
    {
        return ServiceCatalog::query()->orderBy('name')->get();
    }

    public function getEmployeesProperty()
    {
        return User::query()->where('role', User::ROLE_EMPLOYE)->orderBy('name')->get();
    }

    protected function financeService(): FinanceDocumentService
    {
        return app(FinanceDocumentService::class);
    }

    protected function analyticsService(): AdminAnalyticsSnapshotService
    {
        return app(AdminAnalyticsSnapshotService::class);
    }

    protected function baseQuery(): Builder
    {
        return RendezVous::query()
            ->with(['client', 'employe', 'organizationAccount', 'serviceCatalog', 'serviceZone', 'feedback', 'financeInvoice'])
            ->when(filled($this->dateFrom), fn (Builder $q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when(filled($this->dateTo), fn (Builder $q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(filled($this->zoneId), fn (Builder $q) => $q->where('service_zone_id', $this->zoneId))
            ->when(filled($this->serviceId), fn (Builder $q) => $q->where('service_catalog_id', $this->serviceId))
            ->when(filled($this->employeeId), fn (Builder $q) => $q->where('employe_id', $this->employeeId))
            ->when($this->market === 'entreprise', fn (Builder $q) => $q->whereNotNull('organization_account_id'))
            ->when($this->market === 'particulier', fn (Builder $q) => $q->whereNull('organization_account_id'))
            ->when(filled($this->search), fn (Builder $query) => $query->searchStructured($this->search));
    }

    protected function filteredRowsCollection()
    {
        return $this->baseQuery()->orderByDesc('date')->orderByDesc('heure')->get();
    }

    public function getRowsProperty()
    {
        return $this->baseQuery()->orderByDesc('date')->orderByDesc('heure')->paginate(12);
    }

    protected function amountBreakdown(RendezVous $rdv): array
    {
        return $this->financeService()->amountBreakdownFor($rdv);
    }

    protected function amountHtva(RendezVous $rdv): float
    {
        return $this->amountBreakdown($rdv)['subtotal'];
    }

    public function getKpisProperty(): array
    {
        $rows = $this->filteredRowsCollection();
        $turnover = round((float) $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']), 2);
        $margin = round((float) $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['estimated_margin_amount']), 2);
        $completed = $rows->where('status', 'termine');
        $cancelled = $rows->filter(fn (RendezVous $rdv) => in_array($rdv->status, ['annule', 'refuse'], true));
        $feedbacks = $rows->pluck('feedback')->filter();
        $avgSatisfaction = $feedbacks->count() ? round($feedbacks->avg('note'), 2) : 0;
        $avgMissionTime = $completed->count() ? round($completed->avg(fn (RendezVous $rdv) => (int) ($rdv->duree_reelle ?: $rdv->duree ?: 0)), 0) : 0;
        $capacityUsed = $rows->count() ? round(($completed->count() / max($rows->count(), 1)) * 100, 1) : 0;
        $entrepriseCount = $rows->whereNotNull('organization_account_id')->count();
        $invoiceRows = FinanceInvoice::query()->whereIn('rendez_vous_id', $rows->pluck('id'))->get();
        $invoiceHealth = $this->financeService()->invoiceHealthSummary($invoiceRows);

        return [
            'count' => $rows->count(),
            'turnover' => $turnover,
            'margin_estimate' => $margin,
            'completed' => $completed->count(),
            'cancelled' => $cancelled->count(),
            'conversion_rate' => $rows->count() ? round(($completed->count() / $rows->count()) * 100, 1) : 0,
            'avg_ticket' => $rows->count() ? round($turnover / $rows->count(), 2) : 0,
            'avg_satisfaction' => $avgSatisfaction,
            'avg_mission_time' => $avgMissionTime,
            'capacity_used' => $capacityUsed,
            'entreprise_share' => $rows->count() ? round(($entrepriseCount / $rows->count()) * 100, 1) : 0,
            'feedback_coverage' => $rows->count() ? round(($feedbacks->count() / $rows->count()) * 100, 1) : 0,
            'outstanding_balance' => $invoiceHealth['outstanding_balance'],
            'overdue_count' => $invoiceHealth['overdue_count'],
        ];
    }

    public function getZoneAnalyticsProperty()
    {
        return $this->filteredRowsCollection()
            ->groupBy(fn (RendezVous $rdv) => $rdv->serviceZone?->name ?: 'Sans zone')
            ->map(function ($rows, $zoneName) {
                $turnover = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']);
                $margin = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['estimated_margin_amount']);
                $completed = $rows->where('status', 'termine')->count();
                $feedbacks = $rows->pluck('feedback')->filter();

                return [
                    'name' => $zoneName,
                    'count' => $rows->count(),
                    'turnover' => round((float) $turnover, 2),
                    'margin' => round((float) $margin, 2),
                    'completed' => $completed,
                    'avg_satisfaction' => $feedbacks->count() ? round($feedbacks->avg('note'), 2) : null,
                    'cancellation_rate' => $rows->count() ? round(($rows->whereIn('status', ['annule', 'refuse'])->count() / $rows->count()) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('turnover')
            ->take(10)
            ->values();
    }

    public function getServiceAnalyticsProperty()
    {
        return $this->filteredRowsCollection()
            ->groupBy(fn (RendezVous $rdv) => $rdv->service_display_name ?: 'Sans service')
            ->map(function ($rows, $serviceName) {
                $turnover = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']);
                $margin = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['estimated_margin_amount']);
                $completed = $rows->where('status', 'termine')->count();

                return [
                    'name' => $serviceName,
                    'count' => $rows->count(),
                    'turnover' => round((float) $turnover, 2),
                    'margin' => round((float) $margin, 2),
                    'completed' => $completed,
                    'avg_ticket' => $rows->count() ? round($turnover / $rows->count(), 2) : 0,
                ];
            })
            ->sortByDesc('turnover')
            ->take(10)
            ->values();
    }

    public function getEmployeeAnalyticsProperty()
    {
        return $this->filteredRowsCollection()
            ->filter(fn (RendezVous $rdv) => $rdv->employe)
            ->groupBy(fn (RendezVous $rdv) => $rdv->employe?->name)
            ->map(function ($rows, $employeeName) {
                $completed = $rows->where('status', 'termine');
                $feedbacks = $rows->pluck('feedback')->filter();
                $delays = $rows->filter(fn (RendezVous $rdv) => in_array($rdv->status, ['en_route', 'sur_place'], true))->count();
                $margin = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['estimated_margin_amount']);

                return [
                    'name' => $employeeName,
                    'count' => $rows->count(),
                    'completed' => $completed->count(),
                    'avg_time' => $completed->count() ? round($completed->avg(fn (RendezVous $rdv) => (int) ($rdv->duree_reelle ?: $rdv->duree ?: 0)), 0) : null,
                    'avg_satisfaction' => $feedbacks->count() ? round($feedbacks->avg('note'), 2) : null,
                    'delay_signals' => $delays,
                    'margin' => round((float) $margin, 2),
                ];
            })
            ->sortByDesc('completed')
            ->take(10)
            ->values();
    }

    public function getClientAnalyticsProperty()
    {
        return $this->filteredRowsCollection()
            ->groupBy(fn (RendezVous $rdv) => $rdv->organizationAccount?->name ?: $rdv->client?->name ?: 'Client inconnu')
            ->map(function ($rows, $clientName) {
                $turnover = $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']);
                $feedbacks = $rows->pluck('feedback')->filter();

                return [
                    'name' => $clientName,
                    'count' => $rows->count(),
                    'turnover' => round((float) $turnover, 2),
                    'avg_ticket' => $rows->count() ? round($turnover / $rows->count(), 2) : 0,
                    'avg_satisfaction' => $feedbacks->count() ? round($feedbacks->avg('note'), 2) : null,
                    'market' => $rows->first()?->organization_account_id ? 'Entreprise' : 'Particulier',
                ];
            })
            ->sortByDesc('turnover')
            ->take(10)
            ->values();
    }

    public function getHeatmapZonesProperty()
    {
        $zones = $this->zoneAnalytics;
        $max = max(1, (int) ($zones->max('count') ?: 1));

        return $zones->map(function (array $zone) use ($max) {
            $zone['intensity'] = (int) round(($zone['count'] / $max) * 100);
            return $zone;
        });
    }

    public function getMonthTrendProperty()
    {
        return $this->analyticsService()->monthTrend($this->filteredRowsCollection());
    }

    public function exportAnalyticsCsv()
    {
        $rows = $this->filteredRowsCollection();
        $filename = 'analytics_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'reference', 'date', 'heure', 'client', 'employe', 'zone', 'service', 'statut', 'htva', 'marge_estimee', 'note_feedback', 'marche',
            ], ';');

            foreach ($rows as $rdv) {
                fputcsv($handle, [
                    $rdv->booking_reference ?: 'RDV-' . $rdv->id,
                    optional($rdv->date)->format('Y-m-d'),
                    substr((string) $rdv->heure, 0, 5),
                    $rdv->organizationAccount?->name ?: $rdv->client?->name,
                    $rdv->employe?->name,
                    $rdv->serviceZone?->name,
                    $rdv->service_display_name,
                    $rdv->status,
                    number_format($this->amountHtva($rdv), 2, '.', ''),
                    number_format($this->amountBreakdown($rdv)['estimated_margin_amount'], 2, '.', ''),
                    $rdv->feedback?->note,
                    $rdv->organization_account_id ? 'Entreprise' : 'Particulier',
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.admin.analytics-center', [
            'rows' => $this->rows,
            'kpis' => $this->kpis,
            'zoneAnalytics' => $this->zoneAnalytics,
            'serviceAnalytics' => $this->serviceAnalytics,
            'employeeAnalytics' => $this->employeeAnalytics,
            'clientAnalytics' => $this->clientAnalytics,
            'heatmapZones' => $this->heatmapZones,
            'monthTrend' => $this->monthTrend,
        ]);
    }
}
