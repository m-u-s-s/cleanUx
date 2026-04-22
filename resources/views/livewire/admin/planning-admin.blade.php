<div class="p-4 md:p-6 space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-blue-900">🗓️ Planning admin</h2>
            <p class="text-sm text-gray-500">
                Visualisez le planning global et filtrez par employé, date ou statut.
            </p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employé</label>
                <select wire:model.live="filtreEmploye" class="w-full border-gray-300 rounded-lg shadow-sm">
                    <option value="">— Tous —</option>
                    @foreach($employes as $employe)
                        <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" wire:model.live="filtreDate" class="w-full border-gray-300 rounded-lg shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select wire:model.live="filtreStatus" class="w-full border-gray-300 rounded-lg shadow-sm">
                    <option value="">— Tous —</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Total</p>
            <p class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Confirmés</p>
            <p class="text-2xl font-bold text-emerald-700">{{ $stats['confirme'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500">En attente</p>
            <p class="text-2xl font-bold text-amber-600">{{ $stats['attente'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Terminés</p>
            <p class="text-2xl font-bold text-blue-700">{{ $stats['termine'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <livewire:admin.agenda-hebdomadaire />
    </div>
</div>