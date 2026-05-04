<div class="mt-6 overflow-hidden rounded-2xl border bg-white shadow-sm">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">RDV</th>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Zone / ville</th>
                <th class="px-4 py-3">Employé</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
            @forelse($rendezVous as $rdv)
                <tr>
                    <td class="px-4 py-3 font-semibold text-slate-900">
                        #{{ $rdv->id }}
                    </td>
                    <td class="px-4 py-3">
                        {{ $rdv->client?->name ?? $rdv->user?->name ?? 'Client' }}
                    </td>
                    <td class="px-4 py-3">
                        {{ $rdv->postalCode?->city_name ?? $rdv->ville ?? $rdv->city ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        {{ $rdv->employe?->name ?? 'Non assigné' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                wire:click="preview({{ $rdv->id }})"
                                class="rounded-xl border px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Voir scoring
                            </button>

                            <button
                                type="button"
                                wire:click="assign({{ $rdv->id }})"
                                class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700">
                                Assigner
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                        Aucun rendez-vous trouvé.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="p-4">
        {{ $rendezVous->links() }}
    </div>
</div>
