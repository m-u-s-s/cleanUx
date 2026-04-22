<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $title }}</h1>
            <p class="text-sm text-slate-500">{{ __('Référence série :') }} {{ $this->currentRendezVous->recurring_series_id }}</p>
        </div>
        <a href="{{ $backRoute }}" class="rounded-xl border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            {{ __('Retour') }}
        </a>
    </div>

    @if (session()->has('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Éditer la série') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Choisissez si la modification s’applique à une occurrence, aux occurrences futures ou à toute la série.') }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Portée') }}</label>
                    <select wire:model="scope" class="w-full rounded-xl border-slate-300">
                        <option value="occurrence">{{ __('Cette occurrence uniquement') }}</option>
                        <option value="future">{{ __('Cette occurrence et les suivantes') }}</option>
                        <option value="series">{{ __('Toute la série') }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Employé') }}</label>
                    <select wire:model="editEmployeId" class="w-full rounded-xl border-slate-300">
                        <option value="">{{ __('Conserver l’employé actuel') }}</option>
                        @foreach($this->assignableEmployees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Nouvelle date') }}</label>
                    <input type="date" wire:model="editDate" class="w-full rounded-xl border-slate-300">
                    @error('editDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">{{ __('Nouvelle heure') }}</label>
                    <input type="time" wire:model="editHeure" class="w-full rounded-xl border-slate-300">
                    @error('editHeure') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @error('employe_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('heure') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('series') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex flex-wrap gap-3 pt-2">
                <button wire:click="saveChanges" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    {{ __('Sauvegarder') }}
                </button>
                <button wire:click="pauseSeries(scope)" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                    {{ __('Mettre en pause') }}
                </button>
                <button wire:click="resumeSeries(scope)" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                    {{ __('Reprendre') }}
                </button>
                <button wire:click="cancelSeries(scope)" class="rounded-xl border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    {{ __('Annuler') }}
                </button>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Résumé') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Service :') }} {{ $this->currentRendezVous->service_display_name }}</p>
                <p class="text-sm text-slate-500">{{ __('Zone :') }} {{ $this->currentRendezVous->serviceZone?->name ?? __('—') }}</p>
                <p class="text-sm text-slate-500">{{ __('Client :') }} {{ $this->currentRendezVous->client?->name ?? __('—') }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                <p><span class="font-semibold">{{ __('Occurrences :') }}</span> {{ $this->seriesOccurrences->count() }}</p>
                <p><span class="font-semibold">{{ __('Statut actuel :') }}</span> {{ ucfirst($this->currentRendezVous->series_status ?? 'active') }}</p>
                <p><span class="font-semibold">{{ __('Employé actuel :') }}</span> {{ $this->currentRendezVous->employe?->name ?? __('À affecter') }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Occurrences de la série') }}</h2>
                <p class="text-sm text-slate-500">{{ __('Visualisez les rendez-vous qui seront affectés.') }}</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4">{{ __('Date') }}</th>
                        <th class="py-2 pr-4">{{ __('Heure') }}</th>
                        <th class="py-2 pr-4">{{ __('Employé') }}</th>
                        <th class="py-2 pr-4">{{ __('Statut mission') }}</th>
                        <th class="py-2 pr-4">{{ __('Statut série') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($this->seriesOccurrences as $occurrence)
                        <tr @class(['bg-sky-50' => $occurrence->id === $this->currentRendezVous->id])>
                            <td class="py-3 pr-4 font-semibold text-slate-700">{{ $occurrence->series_position ?? '—' }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ optional($occurrence->date)->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ substr((string) $occurrence->heure, 0, 5) }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ $occurrence->employe?->name ?? __('À affecter') }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ ucfirst(str_replace('_', ' ', $occurrence->status ?? '—')) }}</td>
                            <td class="py-3 pr-4 text-slate-700">{{ ucfirst($occurrence->series_status ?? 'active') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
