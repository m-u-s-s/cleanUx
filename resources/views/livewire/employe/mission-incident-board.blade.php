<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <h3 class="text-lg font-semibold text-slate-900">Incident mission</h3>

    @if($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $successMessage }}
        </div>
    @endif

    <input wire:model.defer="title" type="text" placeholder="Titre incident"
           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">

    <textarea wire:model.defer="description" rows="4" placeholder="Description"
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

        <label class="flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 text-sm">
            <input type="checkbox" wire:model.defer="clientVisible">
            Visible client
        </label>
    </div>

    <button wire:click="submit" type="button" class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white">
        Enregistrer incident
    </button>
</div>