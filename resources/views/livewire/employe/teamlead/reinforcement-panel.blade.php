<div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
    <h3 class="text-lg font-bold text-slate-900">Demande de renfort</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <input wire:model="requestedMembers" type="number" min="1" class="rounded-xl border-slate-300" placeholder="Membres demandés">
        <input wire:model="requestedMinutes" type="number" min="15" class="rounded-xl border-slate-300" placeholder="Minutes estimées">
        <select wire:model="reinforcementPriority" class="rounded-xl border-slate-300">
            <option value="normale">Normale</option>
            <option value="haute">Haute</option>
            <option value="urgente">Urgente</option>
        </select>
    </div>
    <textarea wire:model="reinforcementReason" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Pourquoi un renfort est nécessaire ?"></textarea>
    <button wire:click="requestReinforcement" class="inline-flex items-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
        Envoyer la demande de renfort
    </button>
</div>
