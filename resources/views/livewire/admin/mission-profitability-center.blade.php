<x-page-shell
    title="💰 Rentabilité par mission"
    subtitle="Analysez le chiffre d’affaires, les coûts estimés et la marge brute de chaque intervention.">

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Client, employé, référence..."
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Statut mission</label>
                <select wire:model.live="status" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">Tous</option>
                    <option value="planned">Planifiée</option>
                    <option value="assigned">Assignée</option>
                    <option value="en_route">En route</option>
                    <option value="arrived">Arrivé</option>
                    <option value="started">En cours</option>
                    <option value="paused">En pause</option>
                    <option value="completed">Terminée</option>
                    <option value="cancelled">Annulée</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Du</label>
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Au</label>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm">
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Mission</th>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Employé</th>
                        <th class="px-4 py-3 text-left">Prix</th>
                        <th class="px-4 py-3 text-left">Coûts</th>
                        <th class="px-4 py-3 text-left">Marge</th>
                        <th class="px-4 py-3 text-left">Temps</th>
                        <th class="px-4 py-3 text-left">Statut marge</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($missions as $row)
                        @php
                            $mission = $row['mission'];
                            $p = $row['profitability'];
                        @endphp

                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">
                                    {{ $mission->rendezVous?->booking_reference ?? 'Mission #'.$mission->id }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ optional($mission->planned_start_at)->format('d/m/Y H:i') ?? '—' }}
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                {{ $mission->rendezVous?->client?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $mission->leadEmployee?->name ?? '—' }}
                            </td>

                            <td class="px-4 py-3 font-semibold text-slate-900">
                                {{ number_format($p['price'], 2, ',', ' ') }} €
                            </td>

                            <td class="px-4 py-3">
                                <p>{{ number_format($p['total_cost'], 2, ',', ' ') }} €</p>
                                <p class="text-xs text-slate-500">
                                    Employé {{ number_format($p['employee_cost'], 2, ',', ' ') }} € /
                                    Trajet {{ number_format($p['travel_cost'], 2, ',', ' ') }} € /
                                    Matériel {{ number_format($p['material_cost'], 2, ',', ' ') }} €
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-semibold {{ $p['gross_margin'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ number_format($p['gross_margin'], 2, ',', ' ') }} €
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $p['margin_rate'] }}%
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                <p>Prévu : {{ $p['planned_minutes'] }} min</p>
                                <p class="text-xs text-slate-500">
                                    Réel : {{ $p['real_minutes'] ? $p['real_minutes'].' min' : '—' }}
                                </p>
                            </td>

                            <td class="px-4 py-3">
                                <span class="rounded-full px-3 py-1 text-xs font-medium
                                    @class([
                                        'bg-emerald-100 text-emerald-700' => $p['status'] === 'excellent',
                                        'bg-blue-100 text-blue-700' => $p['status'] === 'good',
                                        'bg-amber-100 text-amber-700' => $p['status'] === 'warning',
                                        'bg-red-100 text-red-700' => $p['status'] === 'critical',
                                    ])">
                                    {{ ucfirst($p['status']) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                                Aucune mission trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $missions->links() }}
        </div>
    </div>
</x-page-shell>