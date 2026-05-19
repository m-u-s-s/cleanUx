<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Onboarding v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Onboarding</h1>
                <p class="text-sm text-slate-500">Journeys + progress + step validators cross-modules</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En cours</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['in_progress']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Complétés</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['completed']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Abandonnés</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['abandoned']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Journeys actives</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['active_journeys']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['progress' => 'Progress', 'journeys' => 'Journeys'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'progress')
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="User email..." class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="not_started">Not started</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                    <option value="abandoned">Abandoned</option>
                </select>
                <select wire:model.live="filterRole" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous roles</option>
                    <option value="client">Client</option>
                    <option value="provider">Provider</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'progress')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Journey</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">% complet</th>
                            <th class="px-4 py-2 text-left">Step courant</th>
                            <th class="px-4 py-2 text-left">MAJ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs">{{ $p->user?->email }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->journey?->name }} ({{ $p->journey?->role }})</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-indigo-100 text-indigo-800' => $p->status === 'in_progress',
                                        'bg-emerald-100 text-emerald-800' => $p->status === 'completed',
                                        'bg-amber-100 text-amber-800' => $p->status === 'abandoned',
                                        'bg-slate-100 text-slate-800' => $p->status === 'not_started',
                                    ])>{{ $p->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right text-xs font-bold">{{ number_format((float) $p->percent_complete, 1) }}%</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->current_step_code ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $p->updated_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun progress.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Role</th>
                            <th class="px-4 py-2 text-right">Steps</th>
                            <th class="px-4 py-2 text-right">Version</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $j)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $j->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $j->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $j->role }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ $j->steps_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right text-xs">v{{ $j->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $j->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune journey.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
