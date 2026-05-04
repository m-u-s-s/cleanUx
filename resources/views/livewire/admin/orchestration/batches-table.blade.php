<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                Historique orchestration
            </p>

            <h2 class="mt-1 text-xl font-black text-slate-900">
                Lots récents
            </h2>
        </div>

        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700 ring-1 ring-indigo-100">
            {{ $recentBatches->count() }} lots
        </span>
    </div>

    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs font-black uppercase tracking-wide text-slate-500">
                    <th class="py-3 pe-4">Référence</th>
                    <th class="py-3 pe-4">Nom</th>
                    <th class="py-3 pe-4">Compte</th>
                    <th class="py-3 pe-4">Période</th>
                    <th class="py-3 pe-4">Statut</th>
                    <th class="py-3 pe-4">Jours</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($recentBatches as $batch)
                    <tr class="align-top">
                        <td class="py-3 pe-4 font-black text-slate-900">
                            {{ $batch->reference }}
                        </td>

                        <td class="py-3 pe-4 text-slate-700">
                            {{ $batch->name }}
                        </td>

                        <td class="py-3 pe-4 text-slate-700">
                            {{ $batch->organizationAccount->name ?? '—' }}
                        </td>

                        <td class="py-3 pe-4 text-slate-700">
                            {{ optional($batch->starts_on)->format('d/m/Y') }} → {{ optional($batch->ends_on)->format('d/m/Y') }}
                        </td>

                        <td class="py-3 pe-4">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                {{ $batch->status }}
                            </span>
                        </td>

                        <td class="py-3 pe-4 font-semibold text-slate-700">
                            {{ $batch->days->count() }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-500">
                            Aucun lot de mission pour le moment.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
