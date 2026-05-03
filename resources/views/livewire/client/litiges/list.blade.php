<section class="space-y-4 lg:col-span-2">
    <div class="flex flex-col gap-3 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">Suivi client</p>
            <h3 class="mt-1 text-xl font-black text-slate-900">Mes litiges</h3>
            <p class="mt-1 text-sm text-slate-500">Statut, SLA, preuves et historique de traitement.</p>
        </div>

        <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
            <option value="">Tous les statuts</option>
            <option value="open">Ouvert</option>
            <option value="in_review">En traitement</option>
            <option value="waiting_client">En attente client</option>
            <option value="resolved">Résolu</option>
            <option value="closed">Clôturé</option>
        </select>
    </div>

    @forelse($claims as $claim)
        @include('livewire.client.litiges.claim-card', ['claim' => $claim])
    @empty
        <x-empty-state
            title="Aucun litige"
            message="Vous n’avez pas encore signalé de problème." />
    @endforelse

    <div>
        {{ $claims->links() }}
    </div>
</section>
