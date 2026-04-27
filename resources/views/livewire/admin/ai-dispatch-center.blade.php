<x-page-shell
    title="🤖 IA Dispatch"
    subtitle="Affectation intelligente selon zone, disponibilité, charge, qualité, favoris et urgence.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <input
                type="text"
                wire:model.live="search"
                placeholder="Client, ville, référence..."
                class="rounded-xl border-gray-300 text-sm">

            <select wire:model.live="status" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
            </select>
        </div>
    </div>

    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-left">RDV</th>
                    <th class="px-4 py-3 text-left">Client</th>
                    <th class="px-4 py-3 text-left">Zone</th>
                    <th class="px-4 py-3 text-left">Employé actuel</th>
                    <th class="px-4 py-3 text-left">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y">
                @forelse($rendezVous as $rdv)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">
                                {{ $rdv->booking_reference ?? 'RDV #'.$rdv->id }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $rdv->date?->format('d/m/Y') }} à {{ substr((string) $rdv->heure, 0, 5) }}
                            </p>
                        </td>

                        <td class="px-4 py-3">
                            {{ $rdv->client?->name ?? '—' }}
                        </td>

                        <td class="px-4 py-3">
                            {{ $rdv->serviceZone?->name ?? '—' }}
                        </td>

                        <td class="px-4 py-3">
                            {{ $rdv->employe?->name ?? 'Non assigné' }}
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    wire:click="preview({{ $rdv->id }})"
                                    class="rounded-xl border px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Voir scoring
                                </button>

                                <button
                                    wire:click="assign({{ $rdv->id }})"
                                    class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-700">
                                    🤖 Assigner
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

    @if($previewRdvId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
            <div class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Scoring IA Dispatch</h3>
                        <p class="text-sm text-slate-500">Plus le score est haut, meilleur est le choix.</p>
                    </div>

                    <button wire:click="closePreview" class="text-slate-500 hover:text-slate-900">✕</button>
                </div>

                <div class="space-y-3">
                    @forelse($ranking as $row)
                        <div class="rounded-2xl border p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $row['name'] }}</p>
                                    <p class="text-xs text-slate-500">Employé #{{ $row['employee_id'] }}</p>
                                </div>

                                <div class="text-right">
                                    <p class="text-3xl font-bold text-indigo-700">{{ $row['score'] }}</p>
                                    <p class="text-xs text-slate-500">score total</p>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                @foreach($row['details'] as $label => $value)
                                    <div class="rounded-xl bg-slate-50 border p-2">
                                        <p class="text-slate-500">{{ ucfirst($label) }}</p>
                                        <p class="font-semibold text-slate-900">{{ $value }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Aucun employé disponible.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-page-shell>