<div class="rounded-3xl border border-blue-100 bg-gradient-to-r from-blue-50 via-white to-slate-50 p-5 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                Cockpit administrateur
            </p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                Pilotage global CleanUx
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Suivi des rendez-vous, missions, zones, alertes, qualité, finance et activité terrain.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(Route::has('admin.planning'))
                <a href="{{ route('admin.planning') }}"
                    class="rounded-2xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                    📅 Planning
                </a>
            @endif

            @if(Route::has('admin.missions'))
                <a href="{{ route('admin.missions') }}"
                    class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800">
                    📋 Missions
                </a>
            @endif

            @if(Route::has('admin.finance'))
                <a href="{{ route('admin.finance') }}"
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    💶 Finance
                </a>
            @endif
        </div>
    </div>
</div>
