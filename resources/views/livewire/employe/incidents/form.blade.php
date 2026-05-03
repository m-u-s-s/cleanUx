<section class="max-w-4xl rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <p class="text-xs font-black uppercase tracking-wide text-blue-600">Déclaration employé</p>
        <h2 class="mt-1 text-xl font-black text-slate-900">Nouveau signalement</h2>
        <p class="mt-1 text-sm text-slate-500">
            Reliez l’incident à une mission si possible. Plus le signalement est précis, plus l’admin peut agir vite.
        </p>
    </div>

    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="text-sm font-bold text-slate-700">Mission liée</label>
                <select wire:model="rendezVousId" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">Mission liée (optionnel)</option>
                    @foreach($this->rendezVousOptions as $rdv)
                        <option value="{{ $rdv->id }}">
                            {{ $rdv->booking_reference }} — {{ $rdv->date?->format('d/m/Y') }} — {{ $rdv->client?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-sm font-bold text-slate-700">Type</label>
                <select wire:model="type" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="incident">Incident</option>
                    <option value="materiel">Matériel</option>
                    <option value="securite">Sécurité</option>
                    <option value="qualite">Qualité terrain</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold text-slate-700">Priorité</label>
                <select wire:model="priority" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="faible">Faible</option>
                    <option value="normale">Normale</option>
                    <option value="haute">Haute</option>
                    <option value="critique">Critique</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-bold text-slate-700">Localisation</label>
                <input
                    wire:model="locationNotes"
                    type="text"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Accès, étage, pièce, parking...">
            </div>
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Titre</label>
            <input
                wire:model="title"
                type="text"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                placeholder="Titre de l'incident">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Description</label>
            <textarea
                wire:model="description"
                rows="5"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                placeholder="Décrivez précisément le problème"></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-sm font-bold text-slate-700">Preuves / liens / chemins</label>
            <textarea
                wire:model="attachmentInput"
                rows="3"
                class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                placeholder="1 preuve par ligne"></textarea>
        </div>

        <div class="flex justify-end">
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-black text-white transition hover:bg-blue-700 disabled:opacity-60">
                <span wire:loading.remove>Envoyer</span>
                <span wire:loading>Envoi...</span>
            </button>
        </div>
    </div>
</section>
