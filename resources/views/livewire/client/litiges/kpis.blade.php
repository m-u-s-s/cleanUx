<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-3xl border border-amber-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Ouverts</p>
        <p class="mt-2 text-3xl font-black text-amber-600">
            {{ $claims->where('status', 'open')->count() }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Demandes à analyser par le support.</p>
    </div>

    <div class="rounded-3xl border border-blue-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-wide text-slate-500">En traitement</p>
        <p class="mt-2 text-3xl font-black text-blue-600">
            {{ $claims->where('status', 'in_review')->count() }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Dossiers actuellement suivis.</p>
    </div>

    <div class="rounded-3xl border border-emerald-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Résolus</p>
        <p class="mt-2 text-3xl font-black text-emerald-600">
            {{ $claims->where('status', 'resolved')->count() }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Litiges terminés avec réponse.</p>
    </div>

    <div class="rounded-3xl border border-red-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-black uppercase tracking-wide text-slate-500">Urgents</p>
        <p class="mt-2 text-3xl font-black text-red-600">
            {{ $claims->where('priority', 'urgent')->count() }}
        </p>
        <p class="mt-1 text-xs text-slate-500">À traiter en priorité selon SLA.</p>
    </div>
</section>
