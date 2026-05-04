<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                Génération automatique
            </p>
            <h2 class="mt-1 text-xl font-black text-slate-900">
                Ordres de service approuvés
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Convertissez les work orders validés en missions terrain.
            </p>
        </div>

        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700 ring-1 ring-blue-100">
            {{ $approvedPendingWorkOrders->count() }} attente
        </span>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-black uppercase tracking-wide text-slate-500">
                    <th class="pb-3 pe-4">Réf</th>
                    <th class="pb-3 pe-4">Compte</th>
                    <th class="pb-3 pe-4">Site</th>
                    <th class="pb-3 pe-4">Génération</th>
                    <th class="pb-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($approvedPendingWorkOrders as $workOrder)
                    <tr class="align-top">
                        <td class="py-3 pe-4 font-black text-slate-900">
                            {{ $workOrder->reference }}
                        </td>
                        <td class="py-3 pe-4 text-slate-700">
                            {{ $workOrder->organizationAccount->name ?? '—' }}
                        </td>
                        <td class="py-3 pe-4 text-slate-700">
                            {{ $workOrder->organizationSite->name ?? '—' }}
                        </td>
                        <td class="py-3 pe-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                {{ $workOrder->generation_status ?? 'pending' }}
                            </span>
                        </td>
                        <td class="py-3 text-right">
                            <button
                                type="button"
                                wire:click="generateFromWorkOrder({{ $workOrder->id }})"
                                class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-black text-white transition hover:bg-blue-700">
                                Générer
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-sm text-slate-500">
                            Aucun ordre approuvé à générer.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
