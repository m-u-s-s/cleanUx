<div class="flex flex-col gap-4 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm lg:flex-row lg:items-center lg:justify-between">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600">Réseau opérationnel</p>
        <h1 class="mt-2 text-2xl font-black text-slate-900">Équipes terrain & partenaires</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-600">
            Fondations opérationnelles pour équipes internes, leads et partenaires d’exécution.
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button wire:click="newTeam" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
            Nouvelle équipe
        </button>
        <button wire:click="newPartner" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Nouveau partenaire
        </button>
    </div>
</div>
