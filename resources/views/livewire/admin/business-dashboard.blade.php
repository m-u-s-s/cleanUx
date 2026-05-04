<div class="space-y-6" data-phase2u-root="true">
    @include('livewire.admin.governance.security-checks')

    @includeIf('livewire.admin.readiness.layout-stack')

<div class="space-y-6" data-phase2s-root="true">
    @includeIf('livewire.admin.pilotage.layout-stack')

<x-page-shell
    title="📊 Business Dashboard"
    subtitle="Vue globale de la croissance, du chiffre d’affaires, des clients et des opérations.">

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-business-kpi-card
            title="CA ce mois"
            value="{{ number_format($metrics['revenue_current_month'], 2, ',', ' ') }} €"
            subtitle="Chiffre d’affaires estimé" />

        <x-business-kpi-card
            title="Croissance"
            value="{{ $metrics['revenue_growth'] }}%"
            subtitle="vs mois précédent" />

        <x-business-kpi-card
            title="Réservations"
            value="{{ $metrics['bookings_current_month'] }}"
            subtitle="Ce mois-ci" />

        <x-business-kpi-card
            title="Missions terminées"
            value="{{ $metrics['missions_completed_current_month'] }}"
            subtitle="Ce mois-ci" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-business-kpi-card
            title="Clients"
            value="{{ $metrics['clients_total'] }}"
            subtitle="Total clients" />

        <x-business-kpi-card
            title="Clients Premium"
            value="{{ $metrics['premium_clients'] }}"
            subtitle="Abonnements actifs" />

        <x-business-kpi-card
            title="Employés actifs"
            value="{{ $metrics['employees_total'] }}"
            subtitle="Terrain disponible" />

        <x-business-kpi-card
            title="Litiges ouverts"
            value="{{ $metrics['open_claims'] }}"
            subtitle="À traiter rapidement" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="font-semibold text-slate-900">📈 Évolution CA / réservations</h3>
                    <p class="text-sm text-slate-500">6 dernières semaines</p>
                </div>
            </div>

            <div class="space-y-4">
                @foreach($metrics['weekly_revenue'] as $week)
                    @php
                        $max = max(1, collect($metrics['weekly_revenue'])->max('revenue'));
                        $width = ($week['revenue'] / $max) * 100;
                    @endphp

                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700">{{ $week['label'] }}</span>
                            <span class="text-slate-500">
                                {{ number_format($week['revenue'], 2, ',', ' ') }} €
                                — {{ $week['bookings'] }} RDV
                            </span>
                        </div>

                        <div class="h-3 rounded-full bg-slate-100 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-blue-600"
                                style="width: {{ $width }}%">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <h3 class="font-semibold text-slate-900">💡 Insights rapides</h3>

            <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 text-sm text-blue-800">
                Panier moyen :
                <span class="font-bold">
                    {{ number_format($metrics['avg_booking_value'], 2, ',', ' ') }} €
                </span>
            </div>

            <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-sm text-emerald-800">
                Taux premium :
                <span class="font-bold">
                    {{ $metrics['clients_total'] > 0
                        ? round(($metrics['premium_clients'] / $metrics['clients_total']) * 100, 1)
                        : 0 }}%
                </span>
            </div>

            <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 text-sm text-amber-800">
                Litiges ouverts :
                <span class="font-bold">
                    {{ $metrics['open_claims'] }}
                </span>
            </div>

            <div class="rounded-xl bg-slate-50 border p-4 text-sm text-slate-700">
                Conseil : surveille surtout le CA, le panier moyen, les litiges et le nombre de clients premium.
            </div>
        </div>
    </div>
</x-page-shell>
</div>
</div>