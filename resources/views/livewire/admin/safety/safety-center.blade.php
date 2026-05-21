<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Trust & Safety</p>
                <h1 class="text-2xl font-black text-slate-900">Signalements & Blocages</h1>
                <p class="text-sm text-slate-500">Modération des reports + audit des blocks bi-directionnels.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En attente</p>
                <p class="text-2xl font-black text-rose-600">{{ number_format($stats['pending']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En revue</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['under_review']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Action prise</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['resolved_action']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Blocks actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_blocks']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b">
            <button wire:click="setTab('reports')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'reports' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Signalements
                @if ($stats['pending'] > 0)
                    <span class="inline-block ml-1 rounded-full bg-rose-100 text-rose-700 px-2 py-0.5 text-xs">{{ $stats['pending'] }}</span>
                @endif
            </button>
            <button wire:click="setTab('blocks')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'blocks' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Blocks
            </button>
        </div>

        @if ($tab === 'reports')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche code / email / description..." class="rounded-lg border-slate-300 text-sm flex-1">
                    <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                        <option value="">Tous statuts</option>
                        <option value="pending">En attente</option>
                        <option value="under_review">En revue</option>
                        <option value="resolved_action_taken">Action prise</option>
                        <option value="resolved_no_action">Pas d'action</option>
                        <option value="dismissed">Rejeté</option>
                    </select>
                    <select wire:model.live="categoryFilter" class="rounded-lg border-slate-300 text-sm">
                        <option value="">Toutes catégories</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Signaleur</th>
                            <th class="px-3 py-2">Cible</th>
                            <th class="px-3 py-2">Catégorie</th>
                            <th class="px-3 py-2">Statut</th>
                            <th class="px-3 py-2">Créé</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $r)
                            @php $color = ['pending'=>'rose','under_review'=>'amber','resolved_action_taken'=>'emerald','resolved_no_action'=>'slate','dismissed'=>'slate'][$r->status] ?? 'slate'; @endphp
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ Str::limit($r->code, 14) }}</td>
                                <td class="px-3 py-2 text-xs">{{ $r->reporter?->name }}<br><span class="text-slate-400">{{ $r->reporter?->email }}</span></td>
                                <td class="px-3 py-2 text-xs font-semibold">{{ $r->reported?->name }}<br><span class="text-slate-400">{{ $r->reported?->email }}</span></td>
                                <td class="px-3 py-2 text-xs">{{ $categories[$r->category] ?? $r->category }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $r->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $r->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="openReport({{ $r->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Examiner</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Aucun signalement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $reports->links() }}</div>
            </div>
        @endif

        @if ($tab === 'blocks')
            <div class="rounded-2xl border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Bloqueur</th>
                            <th class="px-3 py-2">Bloqué</th>
                            <th class="px-3 py-2">Raison</th>
                            <th class="px-3 py-2">Depuis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($blocks as $b)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 text-xs">{{ $b->blocker?->name }}<br><span class="text-slate-400">{{ $b->blocker?->email }}</span></td>
                                <td class="px-3 py-2 text-xs">{{ $b->blocked?->name }}<br><span class="text-slate-400">{{ $b->blocked?->email }}</span></td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $b->reason ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $b->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-slate-400">Aucun block.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $blocks->links() }}</div>
            </div>
        @endif

        {{-- Modal résolution --}}
        @if ($selectedReport)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <p class="text-xs uppercase font-bold text-indigo-600">Signalement {{ $selectedReport->code }}</p>
                            <h2 class="text-lg font-bold text-slate-900">{{ $categories[$selectedReport->category] ?? $selectedReport->category }}</h2>
                            <p class="text-xs text-slate-500">{{ $selectedReport->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <button wire:click="closeReport" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">×</button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs uppercase font-bold text-slate-500">Signaleur</p>
                            <p class="text-sm font-semibold">{{ $selectedReport->reporter?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $selectedReport->reporter?->email }}</p>
                        </div>
                        <div class="rounded-lg bg-rose-50 p-3">
                            <p class="text-xs uppercase font-bold text-rose-700">Personne signalée</p>
                            <p class="text-sm font-semibold">{{ $selectedReport->reported?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $selectedReport->reported?->email }}</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <p class="text-xs uppercase font-bold text-slate-500 mb-1">Description</p>
                        <div class="rounded-lg bg-slate-50 p-3 text-sm">{{ $selectedReport->description }}</div>
                    </div>

                    @if ($selectedReport->status !== 'pending' && $selectedReport->status !== 'under_review')
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm">
                            <p class="font-semibold text-emerald-900">Déjà résolu</p>
                            <p class="text-xs text-emerald-700 mt-1">Par {{ $selectedReport->reviewer?->name ?? 'admin' }} le {{ $selectedReport->reviewed_at?->format('d/m H:i') }}</p>
                            @if ($selectedReport->admin_notes)
                                <p class="text-xs text-slate-600 mt-2 italic">« {{ $selectedReport->admin_notes }} »</p>
                            @endif
                        </div>
                    @else
                        <div class="mb-4">
                            <label class="text-xs uppercase font-bold text-slate-500">Notes de résolution</label>
                            <textarea wire:model="resolutionNotes" rows="3" class="w-full rounded-lg border-slate-300 text-sm mt-1" placeholder="Décrire la décision et l'action prise..."></textarea>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button wire:click="resolve('dismissed')" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Rejeter
                            </button>
                            <button wire:click="resolve('resolved_no_action')" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">
                                Pas d'action
                            </button>
                            <button wire:click="resolve('resolved_action_taken')" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                                Action prise
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
