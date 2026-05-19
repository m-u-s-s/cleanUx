<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Realtime v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Realtime / Broadcast</h1>
                <p class="text-sm text-slate-500">Driver : <code class="font-mono">{{ config('broadcasting.default') }}</code></p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Broadcasts 24h</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['total_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Envoyés 24h</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['sent_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Échec 24h</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['failed_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Channels actifs 24h</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['distinct_channels_24h']) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Channel, event class, broadcast_as..."
                   class="flex-1 rounded-xl border-gray-300 text-sm" />
            <select wire:model.live="filterCategory" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes catégories</option>
                <option value="mission_eta">Mission ETA</option>
                <option value="mission_status">Mission status</option>
                <option value="position">Position GPS</option>
                <option value="presence">Presence</option>
                <option value="chat">Chat</option>
                <option value="notification">Notification</option>
            </select>
            <select wire:model.live="filterAudience" class="rounded-xl border-gray-300 text-sm">
                <option value="">Toutes audiences</option>
                <option value="per_user">Per user</option>
                <option value="per_channel">Per channel</option>
                <option value="presence">Presence</option>
                <option value="broadcast">Broadcast</option>
            </select>
            <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                <option value="">Tous statuts</option>
                <option value="queued">Queued</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Channel</th>
                        <th class="px-4 py-2 text-left">Event</th>
                        <th class="px-4 py-2 text-left">Catégorie</th>
                        <th class="px-4 py-2 text-left">Audience</th>
                        <th class="px-4 py-2 text-left">Statut</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($items as $m)
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $m->queued_at?->format('d/m H:i:s') }}</td>
                            <td class="px-4 py-2 text-xs font-mono">{{ $m->channel }}</td>
                            <td class="px-4 py-2 text-xs">
                                <div class="font-semibold">{{ $m->broadcast_as ?? class_basename($m->event_class) }}</div>
                                <div class="text-slate-500 truncate" title="{{ $m->event_class }}">
                                    {{ \Illuminate\Support\Str::limit($m->event_class, 40) }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $m->category }}</td>
                            <td class="px-4 py-2 text-xs">{{ $m->audience }}{{ $m->audience_id ? ' #'.$m->audience_id : '' }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $m->status === 'sent',
                                    'bg-indigo-100 text-indigo-800' => $m->status === 'queued',
                                    'bg-red-100 text-red-800' => $m->status === 'failed',
                                ])>{{ $m->status }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                @if($m->status === 'failed')
                                    <button wire:click="replay({{ $m->id }})"
                                            class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Replay
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun broadcast.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
