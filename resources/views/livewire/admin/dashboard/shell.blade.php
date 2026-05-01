<div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
    <div class="relative overflow-hidden bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-950 px-6 py-7 text-white">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 h-32 w-72 -translate-x-1/2 rounded-full bg-blue-400/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-blue-50 ring-1 ring-white/15">
                        Pilotage CleanUx
                    </span>

                    <span class="rounded-full bg-emerald-400/20 px-3 py-1 text-xs font-bold text-emerald-100 ring-1 ring-emerald-300/20">
                        ● Temps réel
                    </span>
                </div>

                <h1 class="mt-4 text-3xl font-black tracking-tight md:text-4xl">
                    Tableau de bord administrateur
                </h1>

                <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100 md:text-base">
                    Vue consolidée des opérations, urgences, clients premium, qualité, finance et performance terrain.
                </p>

                <div class="mt-5 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-white/15 px-3 py-1 font-semibold ring-1 ring-white/10">
                        Scope : {{ $adminScopeLabel ?? 'Global' }}
                    </span>

                    @if($selectedZone ?? false)
                        <span class="rounded-full bg-white/15 px-3 py-1 font-semibold ring-1 ring-white/10">
                            Zone : {{ $selectedZone->name }}
                        </span>
                    @endif

                    <span class="rounded-full bg-white/15 px-3 py-1 font-semibold ring-1 ring-white/10">
                        {{ now()->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap lg:justify-end">
                @if(Route::has('admin.planning'))
                    <a href="{{ route('admin.planning') }}"
                       class="rounded-2xl bg-white px-4 py-2 text-center text-sm font-black text-blue-700 shadow-sm transition hover:bg-blue-50">
                        🗓️ Planning
                    </a>
                @endif

                @if(Route::has('admin.missions'))
                    <a href="{{ route('admin.missions') }}"
                       class="rounded-2xl bg-white/10 px-4 py-2 text-center text-sm font-black text-white ring-1 ring-white/30 transition hover:bg-white/20">
                        📋 Missions
                    </a>
                @endif

                @if(Route::has('admin.finance'))
                    <a href="{{ route('admin.finance') }}"
                       class="rounded-2xl bg-white/10 px-4 py-2 text-center text-sm font-black text-white ring-1 ring-white/30 transition hover:bg-white/20">
                        💶 Finance
                    </a>
                @endif

                @if(Route::has('admin.outils'))
                    <a href="{{ route('admin.outils') }}"
                       class="rounded-2xl bg-amber-400 px-4 py-2 text-center text-sm font-black text-slate-950 shadow-sm transition hover:bg-amber-300">
                        🛠️ Outils
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($zoneOverview ?? false)
        <div class="grid grid-cols-2 gap-0 border-t border-slate-100 bg-white md:grid-cols-4">
            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Missions aujourd’hui</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $zoneOverview['bookings_today'] }}</p>
            </div>

            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Employés actifs</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $zoneOverview['active_employees'] }}</p>
            </div>

            <div class="border-r border-slate-100 p-4">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Clients</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ $zoneOverview['clients'] }}</p>
            </div>

            <div class="p-4">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">En attente</p>
                <p class="mt-1 text-2xl font-black text-amber-600">{{ $zoneOverview['pending'] }}</p>
            </div>
        </div>
    @endif
</div>