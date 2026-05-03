<aside class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
    <div>
        <p class="text-xs font-black uppercase tracking-wide text-red-600">Support qualité</p>
        <h3 class="mt-1 text-xl font-black text-slate-900">Créer une réclamation</h3>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Ajoutez le rendez-vous concerné, le type de problème, la priorité et des preuves.
        </p>
    </div>

    <div class="mt-6 space-y-4">
        <div>
            <label class="text-sm font-bold text-slate-700">Rendez-vous concerné</label>
            <select wire:model="rendez_vous_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                <option value="">— Aucun / général —</option>
                @foreach($rendezVous as $rdv)
                    <option value="{{ $rdv->id }}">
                        {{ $rdv->date?->format('d/m/Y') }} — {{ $rdv->service_display_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-1">
            <div>
                <label class="text-sm font-bold text-slate-700">Catégorie</label>
                <select wire:model="category" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="quality">Qualité du nettoyage</option>
                    <option value="delay">Retard</option>
                    <option value="damage">Dégât / dommage</option>
                    <option value="billing">Facturation</option>
                    <option value="employee_behavior">Comportement employé</option>
                    <option value="missing_service">Service non réalisé</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold text-slate-700">Priorité</label>
                <select wire:model="priority" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="low">Basse</option>
                    <option value="normal">Normale</option>
                    <option value="high">Haute</option>
                    <option value="urgent">Urgente</option>
                </select>
            </div>
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Titre</label>
            <input
                type="text"
                wire:model="title"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                placeholder="Ex : Nettoyage incomplet">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Description</label>
            <textarea
                wire:model="description"
                rows="5"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                placeholder="Expliquez le problème avec le plus de détails possible..."></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Preuves photo</label>
            <input
                type="file"
                wire:model="photos"
                multiple
                accept="image/*"
                class="mt-1 w-full text-sm">
            @error('photos.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <button
            wire:click="createClaim"
            wire:loading.attr="disabled"
            class="w-full rounded-2xl bg-red-600 px-4 py-3 text-sm font-black text-white transition hover:bg-red-700 disabled:opacity-60">
            <span wire:loading.remove>Envoyer la réclamation</span>
            <span wire:loading>Envoi en cours...</span>
        </button>
    </div>
</aside>
