<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                Filtres qualité
            </p>

            <h2 class="text-2xl font-black text-slate-900">
                Affiner les feedbacks
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Les filtres impactent la liste, les KPIs et les exports PDF/CSV.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach($statusOptions as $value => $label)
                <button wire:click="filterByStatus('{{ $value }}')"
                        class="rounded-full border px-3 py-1.5 text-xs font-black transition
                        {{ $status === $value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="xl:col-span-2">
            <label class="mb-1 block text-sm font-medium text-slate-700">
                Recherche
            </label>

            <input type="text"
                   wire:model.live.debounce.350ms="search"
                   placeholder="Client, employé, service, ville, commentaire…"
                   class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
                Employé
            </label>

            <select wire:model.live="employe_id"
                    class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tous les employés</option>
                @foreach($employes as $employe)
                    <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
                Client
            </label>

            <select wire:model.live="client_id"
                    class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Tous les clients</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
                Par page
            </label>

            <select wire:model.live="perPage"
                    class="w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="5">5</option>
                <option value="8">8</option>
                <option value="12">12</option>
                <option value="20">20</option>
            </select>
        </div>
    </div>

    <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Filtre actif
            </p>

            <p class="mt-1 text-sm font-bold text-slate-800">
                {{ $activeFiltersLabel }}
            </p>
        </div>

        <button wire:click="resetFilters" class="cu-btn-secondary">
            Réinitialiser les filtres
        </button>
    </div>
</section>
