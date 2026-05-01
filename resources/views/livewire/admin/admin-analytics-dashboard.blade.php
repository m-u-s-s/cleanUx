{{-- resources/views/livewire/admin/admin-analytics-dashboard.blade.php --}}

<div class="space-y-6">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Analytics</p>
        <h2 class="mt-2 text-2xl font-black text-slate-900">Centre analytics</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl bg-slate-50 p-4">
                <h3 class="font-bold text-slate-900">Mix marché</h3>
                <p class="text-sm text-slate-500">Répartition particulier, entreprise et premium.</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <h3 class="font-bold text-slate-900">KPIs par zone</h3>
                <p class="text-sm text-slate-500">Lecture opérationnelle par zone de service.</p>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-2xl p-5 border">
            <p class="text-sm text-slate-500">CA total</p>
            <p class="text-2xl font-bold">€{{ number_format($stats['total_revenue'], 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border">
            <p class="text-sm text-slate-500">Marge totale</p>
            <p class="text-2xl font-bold">€{{ number_format($stats['total_margin'], 2) }}</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border">
            <p class="text-sm text-slate-500">Missions</p>
            <p class="text-2xl font-bold">{{ $stats['missions_count'] }}</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border">
            <p class="text-sm text-slate-500">Terminées</p>
            <p class="text-2xl font-bold">{{ $stats['completed_missions'] }}</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border">
            <p class="text-sm text-slate-500">Note moyenne</p>
            <p class="text-2xl font-bold">{{ $stats['average_rating'] }}/5</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-3xl p-6 border">
            <h3 class="font-bold mb-4">CA par mois</h3>
            <div id="revenueChart"></div>
        </div>

        <div class="bg-white rounded-3xl p-6 border">
            <h3 class="font-bold mb-4">Missions par mois</h3>
            <div id="missionsChart"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        const revenueData = @json(array_values($stats['monthly_revenue']));
        const missionsData = @json(array_values($stats['monthly_missions']));

        new ApexCharts(document.querySelector("#revenueChart"), {
            chart: {
                type: 'area',
                height: 300
            },
            series: [{
                name: 'CA',
                data: revenueData
            }],
            xaxis: {
                categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
            }
        }).render();

        new ApexCharts(document.querySelector("#missionsChart"), {
            chart: {
                type: 'bar',
                height: 300
            },
            series: [{
                name: 'Missions',
                data: missionsData
            }],
            xaxis: {
                categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
            }
        }).render();
    });
</script>
@endpush