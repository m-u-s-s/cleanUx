<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Capacité</p>
            <h3 class="text-xl font-black text-slate-900">Limites journalières des employés</h3>
            <p class="text-sm text-slate-500">
                Ajuste les limites de rendez-vous par jour pour éviter les surcharges.
            </p>
        </div>

        @if($employeSelectionne)
            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-200">
                Employé sélectionné
            </span>
        @else
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                Sélection requise
            </span>
        @endif
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <label for="dashboard_employe_id" class="mb-2 block text-sm font-bold text-slate-700">
            Choisir un employé
        </label>

        <select wire:model.live="employeSelectionne"
                id="dashboard_employe_id"
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 md:max-w-md">
            <option value="">-- Sélectionner un employé --</option>

            @foreach($employes as $emp)
                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
            @endforeach
        </select>

        <p class="mt-2 text-xs text-slate-500">
            Les limites affichées concernent la semaine en cours.
        </p>
    </div>

    @if($employeSelectionne)
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach(
                \Carbon\Carbon::now()->startOfWeek()->daysUntil(
                    \Carbon\Carbon::now()->endOfWeek()
                ) as $jour
            )
                @php
                    $isToday = $jour->isToday();
                    $isPast = $jour->isPast() && ! $isToday;
                @endphp

                <div class="rounded-2xl border p-4 shadow-sm
                    {{ $isToday ? 'border-indigo-200 bg-indigo-50' : 'border-slate-200 bg-white' }}
                    {{ $isPast ? 'opacity-70' : '' }}">

                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-slate-900">
                                {{ ucfirst($jour->translatedFormat('l')) }}
                            </p>

                            <p class="text-sm text-slate-500">
                                {{ $jour->translatedFormat('d F Y') }}
                            </p>
                        </div>

                        @if($isToday)
                            <span class="rounded-full bg-indigo-600 px-3 py-1 text-xs font-black text-white">
                                Aujourd’hui
                            </span>
                        @elseif($isPast)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-500">
                                Passé
                            </span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-200">
                                À venir
                            </span>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        @livewire('modifier-limite-jour', [
                            'date' => $jour->format('Y-m-d'),
                            'user_id' => $employeSelectionne,
                            'fromAdmin' => true,
                        ], key($jour->format('Ymd') . '-' . $employeSelectionne))
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-empty-state
            title="Sélectionne un employé"
            message="Choisis un employé pour afficher et modifier ses limites journalières."
            icon="👤"
        />
    @endif
</div>