<div class="mx-auto max-w-7xl px-4 py-6"
     x-data="analyticsDashboard()"
     x-init="initCharts({
        monthlyRevenue: @js($monthlyRevenue->all()),
        statusBreakdown: @js($statusBreakdown->all()),
        satisfactionTrend: @js($satisfactionTrend->all()),
     })"
     wire:ignore.self
>

    {{-- ──────────────────────────── --}}
    {{-- Header                       --}}
    {{-- ──────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tableau de bord</h1>
            <p class="text-sm text-slate-500">{{ $periodLabel }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Sélecteur de période --}}
            <select
                wire:model.live="preset"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm"
            >
                @foreach ($presetOptions as $opt)
                    <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                @endforeach
            </select>

            {{-- Dates custom (visibles uniquement si preset = custom) --}}
            @if ($preset === 'custom')
                <input type="date" wire:model="customFrom" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                <span class="text-xs text-slate-400">→</span>
                <input type="date" wire:model="customTo" class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                <button wire:click="applyCustomDates" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                    Appliquer
                </button>
            @endif

            {{-- Exports --}}
            @if (Route::has('analytics.export.kpis'))
                <a href="{{ route('analytics.export.kpis', ['preset' => $preset, 'from' => $customFrom, 'to' => $customTo]) }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    📊 KPIs CSV
                </a>
            @endif
            @if (Route::has('analytics.export.bookings'))
                <a href="{{ route('analytics.export.bookings', ['preset' => $preset, 'from' => $customFrom, 'to' => $customTo]) }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    📋 Détail CSV
                </a>
            @endif
        </div>
    </div>

    {{-- ──────────────────────────── --}}
    {{-- Alertes business              --}}
    {{-- ──────────────────────────── --}}
    @if ($alerts['overdue_invoices'] + $alerts['pending_approvals'] + $alerts['open_incidents'] + $alerts['bookings_at_risk'] > 0)
        <div class="mb-4 grid gap-2 md:grid-cols-4">
            @if ($alerts['overdue_invoices'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3">
                    <span class="text-xl">⚠</span>
                    <div>
                        <p class="text-xs font-semibold text-red-900">{{ $alerts['overdue_invoices'] }} factures en retard</p>
                        <p class="text-[10px] text-red-700">À régler rapidement</p>
                    </div>
                </div>
            @endif
            @if ($alerts['pending_approvals'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <span class="text-xl">⏱</span>
                    <div>
                        <p class="text-xs font-semibold text-amber-900">{{ $alerts['pending_approvals'] }} approbations en attente</p>
                        <p class="text-[10px] text-amber-700">Demandes à valider</p>
                    </div>
                </div>
            @endif
            @if ($alerts['bookings_at_risk'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-orange-200 bg-orange-50 p-3">
                    <span class="text-xl">📅</span>
                    <div>
                        <p class="text-xs font-semibold text-orange-900">{{ $alerts['bookings_at_risk'] }} RDV à risque</p>
                        <p class="text-[10px] text-orange-700">Prévus dans 48h, pas encore confirmés</p>
                    </div>
                </div>
            @endif
            @if ($alerts['open_incidents'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-purple-200 bg-purple-50 p-3">
                    <span class="text-xl">🚨</span>
                    <div>
                        <p class="text-xs font-semibold text-purple-900">{{ $alerts['open_incidents'] }} incidents ouverts</p>
                        <p class="text-[10px] text-purple-700">À traiter par notre équipe</p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ──────────────────────────── --}}
    {{-- KPI cards                     --}}
    {{-- ──────────────────────────── --}}
    <div class="mb-6 grid gap-3 grid-cols-2 lg:grid-cols-{{ $isCompany ? '6' : '5' }}">
        {{-- CA --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Chiffre d'affaires</p>
            <p class="mt-1 text-xl font-bold text-slate-900">
                {{ number_format($mainKpis['revenue']['value'], 0, ',', ' ') }} €
            </p>
            @if ($mainKpis['revenue']['trend'] !== null)
                <p class="mt-1 inline-flex items-center text-[11px] font-medium {{ $mainKpis['revenue']['trend'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $mainKpis['revenue']['trend'] >= 0 ? '↗' : '↘' }} {{ number_format(abs($mainKpis['revenue']['trend']), 1) }}%
                    <span class="ml-1 text-slate-400">vs précédent</span>
                </p>
            @endif
        </div>

        {{-- RDV --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Rendez-vous</p>
            <p class="mt-1 text-xl font-bold text-slate-900">{{ $mainKpis['bookings_count']['value'] }}</p>
            @if ($mainKpis['bookings_count']['trend'] !== null)
                <p class="mt-1 inline-flex items-center text-[11px] font-medium {{ $mainKpis['bookings_count']['trend'] >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $mainKpis['bookings_count']['trend'] >= 0 ? '↗' : '↘' }} {{ number_format(abs($mainKpis['bookings_count']['trend']), 1) }}%
                </p>
            @endif
        </div>

        {{-- Terminés --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Terminés</p>
            <p class="mt-1 text-xl font-bold text-emerald-600">{{ $mainKpis['completed_count']['value'] }}</p>
            @if ($mainKpis['completed_count']['completion_rate'] !== null)
                <p class="mt-1 text-[11px] text-slate-500">{{ $mainKpis['completed_count']['completion_rate'] }}% complétion</p>
            @endif
        </div>

        {{-- Taux annulation --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Taux annulation</p>
            <p class="mt-1 text-xl font-bold {{ $mainKpis['cancellation_rate']['value'] > 10 ? 'text-red-500' : 'text-slate-900' }}">
                {{ $mainKpis['cancellation_rate']['value'] }}%
            </p>
            @if ($mainKpis['cancellation_rate']['trend'] !== null)
                <p class="mt-1 text-[11px] {{ $mainKpis['cancellation_rate']['trend'] <= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $mainKpis['cancellation_rate']['trend'] <= 0 ? '↘' : '↗' }} {{ number_format(abs($mainKpis['cancellation_rate']['trend']), 1) }}%
                </p>
            @endif
        </div>

        {{-- Satisfaction --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Satisfaction</p>
            @if ($mainKpis['average_rating']['value'])
                <p class="mt-1 text-xl font-bold text-slate-900">
                    {{ $mainKpis['average_rating']['value'] }}<span class="text-sm text-slate-400">/5</span>
                </p>
                <p class="mt-1 text-[11px] text-slate-500">{{ $mainKpis['average_rating']['count'] }} avis</p>
            @else
                <p class="mt-1 text-xl font-bold text-slate-300">—</p>
                <p class="mt-1 text-[11px] text-slate-400">Aucun avis</p>
            @endif
        </div>

        {{-- Sites actifs (uniquement si entreprise) --}}
        @if ($isCompany)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Sites actifs</p>
                <p class="mt-1 text-xl font-bold text-slate-900">
                    {{ $mainKpis['active_sites']['value'] }}<span class="text-sm text-slate-400">/{{ $mainKpis['active_sites']['total'] }}</span>
                </p>
                <p class="mt-1 text-[11px] text-slate-500">sur la période</p>
            </div>
        @endif
    </div>

    {{-- ──────────────────────────── --}}
    {{-- Graphiques principaux         --}}
    {{-- ──────────────────────────── --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        {{-- CA mensuel - 2/3 width --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Chiffre d'affaires mensuel (12 mois)</h3>
            <div id="chart-monthly-revenue" wire:ignore style="height: 280px;"></div>
        </div>

        {{-- Statuts donut - 1/3 width --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Répartition par statut</h3>
            <div id="chart-status-breakdown" wire:ignore style="height: 280px;"></div>
        </div>
    </div>

    {{-- ──────────────────────────── --}}
    {{-- Top services + Top sites      --}}
    {{-- ──────────────────────────── --}}
    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        {{-- Top services --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">Top services</h3>
            @if ($topServices->isEmpty())
                <p class="text-xs text-slate-400 italic">Aucune donnée sur cette période.</p>
            @else
                <div class="space-y-2">
                    @php $maxCount = $topServices->max('count') ?: 1; @endphp
                    @foreach ($topServices as $service)
                        <div>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-700 truncate">{{ $service['service_name'] }}</span>
                                <span class="text-slate-500 font-medium">{{ $service['count'] }} • {{ number_format($service['revenue'], 0, ',', ' ') }} €</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-500" style="width: {{ ($service['count'] / $maxCount) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Top sites (entreprise) ou satisfaction trend (perso) --}}
        @if ($isCompany)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-900">Top sites</h3>
                @if ($topSites->isEmpty())
                    <p class="text-xs text-slate-400 italic">Aucune donnée sur cette période.</p>
                @else
                    <div class="space-y-2">
                        @php $maxCount = $topSites->max('count') ?: 1; @endphp
                        @foreach ($topSites as $site)
                            <div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-700 truncate">{{ $site['site_name'] }}</span>
                                    <span class="text-slate-500 font-medium">{{ $site['count'] }} • {{ number_format($site['revenue'], 0, ',', ' ') }} €</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ ($site['count'] / $maxCount) * 100 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ──────────────────────────── --}}
    {{-- Évolution satisfaction        --}}
    {{-- ──────────────────────────── --}}
    @if ($satisfactionTrend->whereNotNull('avg_rating')->isNotEmpty())
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Évolution de la satisfaction</h3>
            <div id="chart-satisfaction" wire:ignore style="height: 220px;"></div>
        </div>
    @endif
</div>

{{-- Alpine + ApexCharts initialization --}}
@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
        <script>
            function analyticsDashboard() {
                return {
                    monthlyChart: null,
                    statusChart: null,
                    satisfactionChart: null,

                    initCharts(data) {
                        // Délai pour laisser ApexCharts charger via le CDN
                        if (typeof ApexCharts === 'undefined') {
                            setTimeout(() => this.initCharts(data), 100);
                            return;
                        }

                        this.renderMonthlyRevenue(data.monthlyRevenue);
                        this.renderStatusBreakdown(data.statusBreakdown);
                        this.renderSatisfaction(data.satisfactionTrend);

                        // Re-render quand Livewire met à jour les données
                        Livewire.on('analytics:refresh', (newData) => {
                            const detail = Array.isArray(newData) ? newData[0] : newData;
                            if (detail.monthlyRevenue)    this.updateMonthlyRevenue(detail.monthlyRevenue);
                            if (detail.statusBreakdown)   this.updateStatusBreakdown(detail.statusBreakdown);
                            if (detail.satisfactionTrend) this.updateSatisfaction(detail.satisfactionTrend);
                        });
                    },

                    renderMonthlyRevenue(rows) {
                        const el = document.querySelector('#chart-monthly-revenue');
                        if (!el || this.monthlyChart) return;

                        this.monthlyChart = new ApexCharts(el, {
                            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                            colors: ['#3b82f6'],
                            stroke: { curve: 'smooth', width: 2 },
                            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
                            series: [{
                                name: 'CA (€)',
                                data: rows.map(r => r.revenue),
                            }],
                            xaxis: {
                                categories: rows.map(r => r.label),
                                labels: { style: { fontSize: '10px' } },
                            },
                            yaxis: {
                                labels: {
                                    formatter: (v) => new Intl.NumberFormat('fr', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v),
                                    style: { fontSize: '10px' },
                                },
                            },
                            tooltip: {
                                y: { formatter: (v) => new Intl.NumberFormat('fr', { style: 'currency', currency: 'EUR' }).format(v) },
                            },
                            grid: { borderColor: '#f1f5f9' },
                        });
                        this.monthlyChart.render();
                    },

                    renderStatusBreakdown(rows) {
                        const el = document.querySelector('#chart-status-breakdown');
                        if (!el || this.statusChart) return;
                        if (!rows || rows.length === 0) {
                            el.innerHTML = '<p class="pt-12 text-center text-xs text-slate-400">Aucune donnée</p>';
                            return;
                        }

                        this.statusChart = new ApexCharts(el, {
                            chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
                            colors: rows.map(r => r.color),
                            series: rows.map(r => r.count),
                            labels: rows.map(r => r.label),
                            legend: { position: 'bottom', fontSize: '11px' },
                            dataLabels: { enabled: false },
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '70%',
                                        labels: {
                                            show: true,
                                            total: { show: true, label: 'Total', fontSize: '14px' },
                                        },
                                    },
                                },
                            },
                        });
                        this.statusChart.render();
                    },

                    renderSatisfaction(rows) {
                        const el = document.querySelector('#chart-satisfaction');
                        if (!el || this.satisfactionChart) return;

                        const filtered = rows.filter(r => r.avg_rating !== null);
                        if (filtered.length === 0) return;

                        this.satisfactionChart = new ApexCharts(el, {
                            chart: { type: 'line', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
                            colors: ['#f59e0b'],
                            stroke: { curve: 'smooth', width: 3 },
                            markers: { size: 4 },
                            series: [{
                                name: 'Note moyenne',
                                data: rows.map(r => r.avg_rating),
                            }],
                            xaxis: {
                                categories: rows.map(r => r.label),
                                labels: { style: { fontSize: '10px' } },
                            },
                            yaxis: { min: 0, max: 5, tickAmount: 5, labels: { style: { fontSize: '10px' } } },
                            grid: { borderColor: '#f1f5f9' },
                        });
                        this.satisfactionChart.render();
                    },

                    updateMonthlyRevenue(rows) {
                        if (this.monthlyChart) {
                            this.monthlyChart.updateOptions({
                                series: [{ name: 'CA (€)', data: rows.map(r => r.revenue) }],
                                xaxis: { categories: rows.map(r => r.label) },
                            });
                        }
                    },

                    updateStatusBreakdown(rows) {
                        if (this.statusChart) {
                            this.statusChart.updateOptions({
                                series: rows.map(r => r.count),
                                labels: rows.map(r => r.label),
                                colors: rows.map(r => r.color),
                            });
                        }
                    },

                    updateSatisfaction(rows) {
                        if (this.satisfactionChart) {
                            this.satisfactionChart.updateOptions({
                                series: [{ name: 'Note moyenne', data: rows.map(r => r.avg_rating) }],
                                xaxis: { categories: rows.map(r => r.label) },
                            });
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce
