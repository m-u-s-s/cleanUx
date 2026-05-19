<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Quality v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Quality / Inspections</h1>
                <p class="text-sm text-slate-500">
                    Pass ≥ <code>{{ config('quality.thresholds.pass') }}%</code>,
                    Excellent ≥ <code>{{ config('quality.thresholds.excellent') }}%</code>
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">À valider</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['pending_validation']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Litiges</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['disputed']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Validées 7j</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['validated_7d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Score moyen 7j</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['avg_score_7d'] ?? 0, 1) }}%</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['pending' => 'À valider', 'disputes' => 'Litiges', 'history' => 'Historique', 'checklists' => 'Checklists'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'pending')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Soumis</th>
                            <th class="px-4 py-2 text-left">Mission #</th>
                            <th class="px-4 py-2 text-left">Phase</th>
                            <th class="px-4 py-2 text-left">Checklist</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-right">Score</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $i)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $i->submitted_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $i->mission_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->phase }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->checklist?->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->submitter?->email }}</td>
                                <td class="px-4 py-2 text-right text-xs font-bold">
                                    {{ $i->scorePercent() !== null ? number_format($i->scorePercent(), 1) . '%' : '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-xs">
                                    <button wire:click="validate_({{ $i->id }})" class="text-emerald-600 hover:underline mr-2">Valider</button>
                                    <button wire:click="reject({{ $i->id }})" class="text-red-600 hover:underline">Rejeter</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune inspection à valider.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'disputes')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Litige</th>
                            <th class="px-4 py-2 text-left">Mission</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Raison</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $i)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $i->disputed_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $i->mission_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->submitter?->email }}</td>
                                <td class="px-4 py-2 text-xs max-w-md truncate" title="{{ $i->dispute_reason }}">{{ $i->dispute_reason }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    <button wire:click="validate_({{ $i->id }})" class="text-emerald-600 hover:underline mr-2">Valider</button>
                                    <button wire:click="reject({{ $i->id }})" class="text-red-600 hover:underline">Rejeter</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucun litige.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'history')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Validé</th>
                            <th class="px-4 py-2 text-left">Mission</th>
                            <th class="px-4 py-2 text-left">Phase</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $i)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $i->validated_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $i->mission_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->phase }}</td>
                                <td class="px-4 py-2 text-xs">{{ $i->status }}</td>
                                <td class="px-4 py-2 text-right text-xs font-bold">
                                    {{ $i->scorePercent() !== null ? number_format($i->scorePercent(), 1) . '%' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucune validation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Phase</th>
                            <th class="px-4 py-2 text-right">Items</th>
                            <th class="px-4 py-2 text-right">Version</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $c->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->phase }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ $c->items_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right text-xs">v{{ $c->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune checklist.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
