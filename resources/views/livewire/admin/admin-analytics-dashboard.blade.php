{{-- Onglet « Vue d'ensemble » de /admin/analytics/exploration : la page porte le titre,
     cette vue ne pose que ses cartes. --}}

@once
    @push('scripts')
        @vite(['resources/js/apexcharts.js'])
    @endpush
@endonce

<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-5">
        <x-kpi-card title="CA total" :value="locale_currency((float) $stats['total_revenue'])" tone="blue" icon="💶" />
        <x-kpi-card title="Marge totale" :value="locale_currency((float) $stats['total_margin'])" tone="green" icon="📈" />
        <x-kpi-card title="Missions" :value="$stats['missions_count']" tone="slate" icon="📅" />
        <x-kpi-card title="Terminées" :value="$stats['completed_missions']" tone="green" icon="✅" />
        <x-kpi-card title="Note moyenne" :value="$stats['average_rating'].'/5'" tone="amber" icon="⭐" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-app-card title="CA par mois" subtitle="Chiffre d'affaires encaissé, mois par mois.">
            <div id="revenueChart"></div>
        </x-app-card>

        <x-app-card title="Missions par mois" subtitle="Volume de missions, mois par mois.">
            <div id="missionsChart"></div>
        </x-app-card>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        const revenueData = @json(array_values($stats['monthly_revenue']));
        const missionsData = @json(array_values($stats['monthly_missions']));
        const mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];

        // LE GRAPHIQUE LIT LE THEME. Une couleur en dur serait illisible dans l'autre mode ;
        // ces deux jetons sont redefinis en clair comme en sombre par tokens.css.
        const jeton = (nom, repli) =>
            getComputedStyle(document.documentElement).getPropertyValue(nom)?.trim() || repli;

        const encre = jeton('--brio-muted', 'currentColor');
        const grille = jeton('--brio-border', 'currentColor');

        const commun = (hauteur) => ({
            chart: { height: hauteur, toolbar: { show: false }, fontFamily: 'inherit', foreColor: encre },
            grid: { borderColor: grille },
            dataLabels: { enabled: false },
            xaxis: { categories: mois },
        });

        new ApexCharts(document.querySelector('#revenueChart'), {
            ...commun(300),
            chart: { ...commun(300).chart, type: 'area' },
            series: [{ name: 'CA', data: revenueData }],
        }).render();

        new ApexCharts(document.querySelector('#missionsChart'), {
            ...commun(300),
            chart: { ...commun(300).chart, type: 'bar' },
            series: [{ name: 'Missions', data: missionsData }],
        }).render();
    });
</script>
@endpush
