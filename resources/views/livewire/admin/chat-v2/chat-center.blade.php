<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Chat v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Messagerie & Modération</h1>
                <p class="text-sm text-slate-500">Threads booking/dispute/admin + modération PII / toxic</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Threads actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['threads_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Archivés</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['threads_archived']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Flagged (PII)</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['flagged_messages']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Bloqués (toxic)</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['blocked_messages']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['threads' => 'Threads', 'flagged' => 'Flagged', 'blocked' => 'Bloqués'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'threads')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                    <option value="locked">Locked</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'threads')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Contexte</th>
                            <th class="px-4 py-2 text-left">Titre</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Messages</th>
                            <th class="px-4 py-2 text-left">Flagged</th>
                            <th class="px-4 py-2 text-left">Dernier</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $t)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $t->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->context_type ?? '—' }}{{ $t->context_id ? ' #' . $t->context_id : '' }}</td>
                                <td class="px-4 py-2 text-xs">{{ \Illuminate\Support\Str::limit($t->title ?? '—', 40) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $t->status === 'active' && ! $t->is_archived,
                                        'bg-slate-100 text-slate-800' => $t->is_archived,
                                        'bg-red-100 text-red-800' => $t->status === 'locked',
                                    ])>{{ $t->is_archived ? 'archived' : $t->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $t->message_count }}</td>
                                <td class="px-4 py-2 text-xs">{{ $t->flagged_count > 0 ? $t->flagged_count : '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($t->last_message_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! $t->is_archived)
                                        <button wire:click="archiveThread({{ $t->id }})" class="text-amber-600 hover:underline">Archiver</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun thread.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Thread</th>
                            <th class="px-4 py-2 text-left">Sender</th>
                            <th class="px-4 py-2 text-left">Body</th>
                            <th class="px-4 py-2 text-left">Raison</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $m)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($m->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $m->thread?->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->sender?->email ?? $m->sender_role }}</td>
                                <td class="px-4 py-2 text-xs">{{ \Illuminate\Support\Str::limit($m->displayBody(), 80) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $m->moderation_reason ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    <button wire:click="moderateApprove({{ $m->id }})" class="text-emerald-600 hover:underline">Approuver</button>
                                    @if(! $m->is_deleted)
                                        <button wire:click="moderateDelete({{ $m->id }})" class="text-red-600 hover:underline">Supprimer</button>
                                    @endif
                                    @if($m->moderation_status !== 'blocked')
                                        <button wire:click="moderateBlock({{ $m->id }})" class="text-amber-600 hover:underline">Bloquer</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun message.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
