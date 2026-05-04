<div class="rounded-2xl border bg-white p-5 shadow-sm">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <input
            type="text"
            wire:model.live="search"
            placeholder="Client, ville, référence..."
            class="rounded-xl border-gray-300 text-sm">

        <select wire:model.live="status" class="rounded-xl border-gray-300 text-sm">
            <option value="">Tous</option>
            <option value="en_attente">En attente</option>
            <option value="confirme">Confirmé</option>
            <option value="en_route">En route</option>
            <option value="sur_place">Sur place</option>
        </select>

        <div class="rounded-xl bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-700">
            IA Dispatch actif
        </div>
    </div>
</div>
