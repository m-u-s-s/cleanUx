{{-- FILTRES --}}
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                        Filtres de pilotage
                    </p>

                    <h2 class="text-2xl font-black text-slate-900">
                        Affiner la charge opérationnelle
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Ces filtres adaptent les KPIs, les alertes, la charge équipe et l’agenda hebdomadaire.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="semainePrecedente"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        ← Semaine précédente
                    </button>

                    <button
                        type="button"
                        wire:click="semaineSuivante"
                        class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        Semaine suivante →
                    </button>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Recherche globale
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.350ms="recherche"
                        placeholder="Client, employé, ville, service, référence…"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Employé
                    </label>

                    <select
                        wire:model.live="filtreEmploye"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les employés</option>

                        @foreach($employes as $employe)
                            <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Date focus
                    </label>

                    <input
                        type="date"
                        wire:model.live="filtreDate"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Statut
                    </label>

                    <select
                        wire:model.live="filtreStatus"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Tous les statuts</option>

                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">
                        Priorité
                    </label>

                    <select
                        wire:model.live="filtrePriorite"
                        class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Toutes les priorités</option>

                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>
