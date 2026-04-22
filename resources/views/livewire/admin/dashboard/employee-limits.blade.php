<div class="rounded bg-white p-5 shadow">
    <h2 class="mb-4 text-lg font-semibold text-blue-900">🧩 Limites journalières des employés</h2>

    <div class="mb-4">
        <label for="dashboard_employe_id" class="text-sm font-medium text-gray-700">Choisir un employé :</label>
        <select wire:model="employeSelectionne" id="dashboard_employe_id" class="mt-1 block w-64 rounded border-gray-300 text-sm shadow-sm">
            <option value="">-- Sélectionner --</option>
            @foreach($employes as $emp)
                <option value="{{ $emp->id }}">{{ $emp->name }}</option>
            @endforeach
        </select>
    </div>

    @if($employeSelectionne)
        <div class="space-y-2">
            @foreach(
                \Carbon\Carbon::now()->startOfWeek()->daysUntil(
                    \Carbon\Carbon::now()->endOfWeek()
                ) as $jour
            )
                <div class="flex items-center justify-between rounded bg-gray-50 p-2">
                    <div class="w-1/3 text-sm font-medium text-gray-700">
                        {{ $jour->translatedFormat('l d F') }}
                    </div>
                    <div class="w-2/3">
                        @livewire('modifier-limite-jour', [
                            'date' => $jour->format('Y-m-d'),
                            'user_id' => $employeSelectionne,
                            'fromAdmin' => true,
                        ], key($jour->format('Ymd') . '-' . $employeSelectionne))
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
