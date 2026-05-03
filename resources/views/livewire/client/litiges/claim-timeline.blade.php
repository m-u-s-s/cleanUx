<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
    <p class="mb-3 text-sm font-black text-slate-900">Suivi du dossier</p>

    <div class="flex flex-wrap gap-2 text-xs">
        <span class="rounded-full px-3 py-1 font-black {{ in_array($claim->status, ['open','in_review','waiting_client','resolved','closed']) ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">
            Ouvert
        </span>

        <span class="rounded-full px-3 py-1 font-black {{ in_array($claim->status, ['in_review','waiting_client','resolved','closed']) ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500' }}">
            Analyse support
        </span>

        <span class="rounded-full px-3 py-1 font-black {{ in_array($claim->status, ['waiting_client']) ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-500' }}">
            Attente client
        </span>

        <span class="rounded-full px-3 py-1 font-black {{ in_array($claim->status, ['resolved','closed']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
            Résolu
        </span>
    </div>
</div>
