<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">
                Lots planifiés
            </p>
            <h2 class="mt-1 text-xl font-black text-slate-900">
                Lots récents
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Matérialisez les missions préparées par l’orchestration terrain.
            </p>
        </div>

        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700 ring-1 ring-emerald-100">
            {{ $recentBatches->count() }} lots
        </span>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-black uppercase tracking-wide text-slate-500">
                    <th class="pb-3 pe-4">Lot</th>
                    <th class="pb-3 pe-4">Compte</th>
                    <th class="pb-3 pe-4">Statut</th>
                    <th class="pb-3 pe-4">Missions</th>
                    <th class="pb-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($recentBatches as $batch)
                    <tr class="align-top">
                        <td class="py-3 pe-4 font-black text-slate-900">
                            {{ $batch->reference ?? $batch->batch_code }}
                        </td>
                        <td class="py-3 pe-4 text-slate-700">
                            {{ $batch->organizationAccount->name ?? '—' }}
                        </td>
                        <td class="py-3 pe-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                {{ $batch->generation_status ?? $batch->status }}
                            </span>
                        </td>
                        <td class="py-3 pe-4 font-semibold text-slate-700">
                            {{ $batch->generated_missions_count ?? 0 }}
                        </td>
                        <td class="py-3 text-right">
                            <button
                                type="button"
                                wire:click="materializeBatch({{ $batch->id }})"
                                class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white transition hover:bg-emerald-700">
                                Matérialiser
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-slate-500">
                            Aucun lot récent.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
