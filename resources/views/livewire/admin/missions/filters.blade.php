<div class="bg-white rounded-2xl shadow border p-4">
    <div class="mb-4">
        <h3 class="text-lg font-bold text-slate-900">Filtres missions</h3>
        <p class="text-sm text-slate-500">
            Filtrez les missions par employé, statut, priorité ou recherche libre.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input
            type="text"
            wire:model.live="search"
            placeholder="Service, client, employé, ville..."
            class="w-full border-gray-300 rounded-lg shadow-sm"
        >

        <select wire:model.live="filtreEmploye" class="w-full border-gray-300 rounded-lg shadow-sm">
            <option value="">— Tous les employés —</option>
            @foreach($employes as $employe)
                <option value="{{ $employe->id }}">{{ $employe->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreStatus" class="w-full border-gray-300 rounded-lg shadow-sm">
            <option value="">— Tous les statuts —</option>
            <option value="en_attente">En attente</option>
            <option value="confirme">Confirmé</option>
            <option value="en_route">En route</option>
            <option value="sur_place">Sur place</option>
            <option value="termine">Terminé</option>
            <option value="refuse">Refusé</option>
        </select>

        <select wire:model.live="filtrePriorite" class="w-full border-gray-300 rounded-lg shadow-sm">
            <option value="">— Toutes les priorités —</option>
            <option value="normale">Normale</option>
            <option value="haute">Haute</option>
            <option value="urgente">Urgente</option>
        </select>
    </div>
</div>
