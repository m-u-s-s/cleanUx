<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                Filtres rapides
            </p>
            <h3 class="text-xl font-black text-slate-900">
                Affiner le dashboard
            </h3>
            <p class="text-sm text-slate-500">
                Filtre les données par employé ou zone.
            </p>
        </div>

        <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 lg:max-w-2xl">
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                    Employé
                </label>

                <select wire:model.live="filtreEmploye"
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Tous les employés</option>

                    @foreach($employes as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-slate-500">
                    Zone
                </label>

                <select wire:model.live="filtreZone"
                        @disabled($zoneScopeLocked)
                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400">
                    <option value="">Toutes les zones</option>

                    @foreach($availableZones as $zone)
                        <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if($filtreEmploye || $filtreZone)
        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            @if($filtreEmploye)
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-200">
                    Employé filtré
                </span>
            @endif

            @if($filtreZone)
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-200">
                    Zone filtrée
                </span>
            @endif

            @if($zoneScopeLocked)
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700 ring-1 ring-amber-200">
                    Scope zone verrouillé
                </span>
            @endif
        </div>
    @endif
</div>