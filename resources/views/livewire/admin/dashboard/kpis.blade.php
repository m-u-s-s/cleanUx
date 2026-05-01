<div class="space-y-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                Indicateurs clés
            </p>
            <h2 class="mt-1 text-xl font-black text-slate-900">
                Santé opérationnelle
            </h2>
        </div>

        <p class="text-sm text-slate-500">
            Vue rapide des urgences, charges et missions.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <x-kpi-card title="En attente" :value="$adminKpis['en_attente'] ?? 0" tone="amber" icon="⏳" />
        <x-kpi-card title="Urgences" :value="$adminKpis['urgentes_vieilles'] ?? 0" tone="red" icon="🚨" />
        <x-kpi-card title="Missions longues" :value="$adminKpis['missions_longues'] ?? 0" tone="orange" icon="🕒" />
        <x-kpi-card title="Surcharges" :value="$adminKpis['employes_surcharges'] ?? 0" tone="rose" icon="👥" />
        <x-kpi-card title="Aujourd’hui" :value="$adminKpis['missions_du_jour'] ?? 0" tone="blue" icon="📅" />
        <x-kpi-card title="Terminées" :value="$adminKpis['missions_terminees_mois'] ?? 0" tone="green" icon="✅" />
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-amber-700">Clients Premium actifs</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ $premiumClientsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Clients avec abonnement actif</p>
                </div>

                <span class="rounded-2xl bg-amber-100 px-3 py-2 text-lg">⭐</span>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-slate-600">Clients Standard</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ $standardClientsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Clients classiques</p>
                </div>

                <span class="rounded-2xl bg-slate-100 px-3 py-2 text-lg">👤</span>
            </div>
        </div>

        <div class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-emerald-700">Abonnements actifs</p>
                    <p class="mt-2 text-3xl font-black text-slate-900">{{ $activeSubscriptionsCount ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Revenus récurrents potentiels</p>
                </div>

                <span class="rounded-2xl bg-emerald-100 px-3 py-2 text-lg">🔁</span>
            </div>
        </div>
    </div>
</div>