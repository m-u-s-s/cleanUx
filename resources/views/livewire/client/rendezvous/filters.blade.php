<div class="bg-white rounded-2xl shadow-sm border p-5">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Recherche</label>
            <input
                type="text"
                wire:model.live.debounce.350ms="search"
                placeholder="Service, ville, adresse, employé..."
                class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Statut</label>
            <select wire:model.live="filtreStatus" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
                <option value="">Tous</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
                <option value="annule">Annulé</option>
                <option value="refuse">Refusé</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tri</label>
            <select wire:model.live="tri" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
                <option value="asc">Plus proche d’abord</option>
                <option value="desc">Plus récent d’abord</option>
            </select>
        </div>
    </div>
</div>
