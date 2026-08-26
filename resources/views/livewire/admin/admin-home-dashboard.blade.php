@once
    @push('scripts')
        @vite(['resources/js/apexcharts.js'])
    @endpush
@endonce

<div class="brio-page space-y-6" wire:poll.30s>

    <div class="brio-page-header">
        <div>
            <p class="brio-eyebrow">Tableau de bord</p>
            <h1 class="brio-section-title mt-2">Vue d'ensemble</h1>
            <p class="brio-section-subtitle">Actualisation automatique toutes les 30 secondes.</p>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
        <div class="brio-kpi">
            <p class="brio-kpi-label">Réservations aujourd'hui</p>
            <p class="brio-kpi-value">{{ $bookingsToday }}</p>
        </div>

        <div class="brio-kpi">
            <p class="brio-kpi-label">Missions actives</p>
            <p class="brio-kpi-value">{{ $activeMissions }}</p>
        </div>

        <div class="brio-kpi">
            <p class="brio-kpi-label">Prestataires en ligne</p>
            <p class="brio-kpi-value">{{ $providersOnline }}</p>
        </div>

        <div class="brio-kpi">
            <p class="brio-kpi-label">CA aujourd'hui (EUR)</p>
            <p class="brio-kpi-value">{{ number_format($revenueToday, 2, ',', ' ') }}</p>
        </div>

        <div class="brio-kpi">
            <p class="brio-kpi-label">Versements en attente</p>
            <p class="brio-kpi-value {{ $pendingPayouts > 0 ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ $pendingPayouts }}</p>
        </div>

        <div class="brio-kpi">
            <p class="brio-kpi-label">Webhooks échoués (24h)</p>
            <p class="brio-kpi-value {{ $webhookFailures24h > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $webhookFailures24h }}</p>
        </div>
    </div>

    {{-- Tendance 7 jours --}}
    <div class="brio-card" wire:ignore>
        <h3 class="brio-section-title mb-4">Tendance 7 jours — Réservations</h3>
        <div id="bookings-trend-chart"></div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Recent disputes --}}
        <div class="brio-card">
            <h3 class="brio-section-title mb-4">Litiges en cours</h3>

            @forelse($recentDisputes as $dispute)
            <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0 dark:border-slate-700">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $dispute->reference }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $dispute->subject }}</p>
                </div>
                <span @class([
                    'ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold',
                    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $dispute->status === 'open',
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'   => $dispute->status === 'assigned',
                    'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' => $dispute->status === 'investigating',
                ])>{{ $dispute->status }}</span>
            </div>
            @empty
            <p class="py-4 text-center text-sm text-slate-400 dark:text-slate-500">Aucun litige en cours</p>
            @endforelse
        </div>

        {{-- Recent bookings --}}
        <div class="brio-card">
            <h3 class="brio-section-title mb-4">Dernières réservations</h3>

            <div class="overflow-x-auto">
                <table class="brio-table w-full">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Service</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentBookings as $booking)
                        <tr>
                            <td class="font-mono text-xs dark:text-slate-300">{{ $booking->reference }}</td>
                            <td class="dark:text-slate-300">{{ $booking->serviceCatalog?->name ?? '—' }}</td>
                            <td>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-bold',
                                    'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'     => in_array($booking->status, ['en_attente', 'draft']),
                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'       => $booking->status === 'confirme',
                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $booking->status === 'completed',
                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'         => $booking->status === 'annule',
                                ])>{{ $booking->status }}</span>
                            </td>
                            <td class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $booking->scheduled_date ? \Carbon\Carbon::parse($booking->scheduled_date)->format('d/m') : $booking->created_at?->format('d/m') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-sm text-slate-400 dark:text-slate-500">Aucune réservation</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script>
(function () {
    function renderBookingsTrendChart() {
        var el = document.getElementById('bookings-trend-chart');
        if (!el || typeof ApexCharts === 'undefined') return;

        var data = @json($bookingsTrend);

        new ApexCharts(el, {
            chart: {
                type: 'area',
                height: 240,
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 600 },
            },
            series: [{ name: 'Réservations', data: data.map(function (d) { return d.count; }) }],
            xaxis: { categories: data.map(function (d) { return d.date; }), labels: { style: { fontSize: '11px' } } },
            yaxis: { min: 0, tickAmount: 4, labels: { style: { fontSize: '11px' } } },
            colors: ['#6366f1'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            grid: { borderColor: window.brioJeton('--brio-border', 'rgba(100,116,139,0.1)'), strokeDashArray: 3 },
            tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
        }).render();
    }

    document.addEventListener('livewire:navigated', renderBookingsTrendChart);
    document.addEventListener('livewire:initialized', renderBookingsTrendChart);
    if (document.readyState !== 'loading') {
        renderBookingsTrendChart();
    } else {
        document.addEventListener('DOMContentLoaded', renderBookingsTrendChart);
    }
})();
</script>
@endpush
