    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Client, entreprise, site..."
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Statut</label>
                <select wire:model.live="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option value="pending_manager">En attente manager</option>
                    <option value="pending_finance">En attente finance</option>
                    <option value="approved">Approuvé</option>
                    <option value="rejected">Refusé</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>
        </div>
    </div>
