<div class="space-y-4">
    <div>
        <p class="text-xs font-black uppercase tracking-wide text-rose-600">Incident mission</p>
        <h3 class="mt-1 text-lg font-black text-slate-900">Créer un incident lié à cette mission</h3>
        <p class="mt-1 text-sm text-slate-500">
            Utilisez ce formulaire pour signaler un problème directement relié à la mission ouverte.
        </p>
    </div>

    @if($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
            {{ $successMessage }}
        </div>
    @endif

    <div class="space-y-3">
        <input
            wire:model.defer="title"
            type="text"
            placeholder="Titre incident"
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
        @error('title') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

        <textarea
            wire:model.defer="description"
            rows="4"
            placeholder="Description"
            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></textarea>

        <div class="grid gap-3 md:grid-cols-3">
            <select wire:model.defer="incidentType" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="general">Général</option>
                <option value="access">Accès</option>
                <option value="material">Matériel</option>
                <option value="damage">Dégât</option>
                <option value="client_request">Demande client</option>
            </select>

            <select wire:model.defer="severity" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">
                <option value="low">Faible</option>
                <option value="medium">Moyen</option>
                <option value="high">Élevé</option>
                <option value="critical">Critique</option>
            </select>

            <label class="flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" wire:model.defer="clientVisible" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                Visible client
            </label>
        </div>

        <button
            wire:click="submit"
            wire:loading.attr="disabled"
            type="button"
            class="rounded-2xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 disabled:opacity-60">
            <span wire:loading.remove>Enregistrer incident</span>
            <span wire:loading>Enregistrement...</span>
        </button>
    </div>
</div>
