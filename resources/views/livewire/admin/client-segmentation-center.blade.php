<x-page-shell
    title="👥 Segmentation clients"
    subtitle="Identifiez vos clients premium, fidèles, à risque, inactifs ou à forte valeur.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
                <label class="text-sm font-medium text-slate-700">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Nom ou email..."
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Segment</label>
                <select wire:model.live="segment" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option value="Premium">Premium</option>
                    <option value="Entreprise">Entreprise</option>
                    <option value="Client fidèle">Client fidèle</option>
                    <option value="Forte valeur">Forte valeur</option>
                    <option value="Client à risque">Client à risque</option>
                    <option value="Nouveau client">Nouveau client</option>
                    <option value="Inactif">Inactif</option>
                    <option value="Multi-sites">Multi-sites</option>
                </select>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Segments</th>
                        <th class="px-4 py-3 text-left">RDV</th>
                        <th class="px-4 py-3 text-left">CA estimé</th>
                        <th class="px-4 py-3 text-left">Litiges</th>
                        <th class="px-4 py-3 text-left">Fidélité</th>
                        <th class="px-4 py-3 text-left">Risque</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($clients as $client)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $client['name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $client['email'] }}</p>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($client['labels'] as $label)
                                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                            {{ $label }}
                                        </span>
                                    @empty
                                        <span class="text-slate-400">—</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                {{ $client['total_bookings'] }}
                                <span class="text-xs text-slate-500">
                                    / {{ $client['completed_bookings'] }} terminés
                                </span>
                            </td>

                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ number_format($client['total_revenue'], 2, ',', ' ') }} €
                            </td>

                            <td class="px-4 py-3">
                                {{ $client['claims_count'] }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="w-32 rounded-full bg-slate-100 h-2 overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: {{ $client['loyalty_score'] }}%"></div>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">{{ $client['loyalty_score'] }}/100</p>
                            </td>

                            <td class="px-4 py-3">
                                <div class="w-32 rounded-full bg-slate-100 h-2 overflow-hidden">
                                    <div class="h-full bg-red-500" style="width: {{ $client['risk_score'] }}%"></div>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">{{ $client['risk_score'] }}/100</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                Aucun client trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $clients->links() }}
        </div>
    </div>
</x-page-shell>