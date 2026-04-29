<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm font-bold uppercase tracking-wide text-indigo-600">
                Priorité du jour
            </p>
            <h2 class="mt-1 text-2xl font-black text-slate-900">
                Ce qui doit être traité maintenant
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Vue rapide pour éviter les retards, oublis et missions bloquées.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.missions') }}"
               class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                Voir missions
            </a>

            @if(Route::has('admin.planning'))
                <a href="{{ route('admin.planning') }}"
                   class="rounded-xl border px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Planning
                </a>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border bg-amber-50 p-4">
            <p class="text-xs font-bold uppercase text-amber-700">En attente</p>
            <p class="mt-2 text-3xl font-black text-amber-800">
                {{ $adminKpis['en_attente'] ?? 0 }}
            </p>
            <p class="text-sm text-amber-700">Demandes à traiter</p>
        </div>

        <div class="rounded-2xl border bg-red-50 p-4">
            <p class="text-xs font-bold uppercase text-red-700">Urgences anciennes</p>
            <p class="mt-2 text-3xl font-black text-red-800">
                {{ $adminKpis['urgentes_vieilles'] ?? 0 }}
            </p>
            <p class="text-sm text-red-700">À prioriser</p>
        </div>

        <div class="rounded-2xl border bg-blue-50 p-4">
            <p class="text-xs font-bold uppercase text-blue-700">Employés surchargés</p>
            <p class="mt-2 text-3xl font-black text-blue-800">
                {{ $adminKpis['employes_surcharges'] ?? 0 }}
            </p>
            <p class="text-sm text-blue-700">Charge à équilibrer</p>
        </div>

        <div class="rounded-2xl border bg-emerald-50 p-4">
            <p class="text-xs font-bold uppercase text-emerald-700">Feedback rate</p>
            <p class="mt-2 text-3xl font-black text-emerald-800">
                {{ round($feedbackRate ?? 0) }}%
            </p>
            <p class="text-sm text-emerald-700">Satisfaction à suivre</p>
        </div>
    </div>
</div>