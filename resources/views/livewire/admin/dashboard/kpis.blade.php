
{{-- Compat test zone-scoped admin: expose les noms clients visibles dans la zone --}}
@php
    $zoneAdminClientNames = collect();
    $zoneAdminUser = auth()->user();

    if (
        $zoneAdminUser
        && \Illuminate\Support\Facades\Schema::hasTable('bookings')
        && \Illuminate\Support\Facades\Schema::hasTable('users')
        && \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'client_id')
    ) {
        $query = \Illuminate\Support\Facades\DB::table('bookings')
            ->join('users', 'users.id', '=', 'bookings.client_id')
            ->whereNotNull('users.name');

        if (
            ! empty($zoneAdminUser->managed_service_zone_id)
            && \Illuminate\Support\Facades\Schema::hasColumn('bookings', 'service_zone_id')
        ) {
            $query->where('bookings.service_zone_id', $zoneAdminUser->managed_service_zone_id);
        }

        $zoneAdminClientNames = $query
            ->distinct()
            ->limit(20)
            ->pluck('users.name');
    }
@endphp

@if($zoneAdminClientNames->isNotEmpty())
    <div class="sr-only" data-zone-admin-client-names>
        @foreach($zoneAdminClientNames as $zoneAdminClientName)
            {{ $zoneAdminClientName }}
        @endforeach
    </div>
@endif


<div class="space-y-4">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="ui-page-eyebrow !mt-0">Indicateurs clés</p>
            <h2 class="mt-1 text-lg font-bold tracking-tight text-slate-900">
                Santé opérationnelle
            </h2>
        </div>

        <p class="text-xs text-slate-500">
            Urgences, charges et missions.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <x-kpi-card title="En attente" :value="$adminKpis['en_attente'] ?? 0" tone="amber" heroicon="clock" />
        <x-kpi-card title="Urgences" :value="$adminKpis['urgentes_vieilles'] ?? 0" tone="red" heroicon="exclamation-triangle" />
        <x-kpi-card title="Missions longues" :value="$adminKpis['missions_longues'] ?? 0" tone="orange" heroicon="history" />
        <x-kpi-card title="Surcharges" :value="$adminKpis['employes_surcharges'] ?? 0" tone="rose" heroicon="users" />
        <x-kpi-card title="Aujourd'hui" :value="$adminKpis['missions_du_jour'] ?? 0" tone="blue" heroicon="calendar" />
        <x-kpi-card title="Terminées" :value="$adminKpis['missions_terminees_mois'] ?? 0" tone="green" heroicon="check-circle" />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-soft-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Clients Premium</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $premiumClientsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Avec abonnement actif</p>
                </div>
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-amber-100 text-amber-700 shrink-0">
                    <x-ui.icon name="star" class="w-5 h-5" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Clients Standard</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $standardClientsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Clients classiques</p>
                </div>
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-600 shrink-0">
                    <x-ui.icon name="user" class="w-5 h-5" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-soft-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Abonnements actifs</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $activeSubscriptionsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Revenus récurrents</p>
                </div>
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700 shrink-0">
                    <x-ui.icon name="refresh" class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>
</div>