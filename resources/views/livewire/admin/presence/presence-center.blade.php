<div class="py-6" wire:poll.15s>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Live status</p>
                <h1 class="text-2xl font-black text-slate-900">Presence prestataires</h1>
                <p class="text-sm text-slate-500">Online/Busy/Break/Offline en temps réel. Auto-refresh 15s.</p>
            </div>
            <div class="flex gap-2">
                <button wire:click="scanStale" class="rounded-xl bg-amber-100 text-amber-700 px-4 py-2 text-sm font-semibold hover:bg-amber-200">
                    Scanner stales
                </button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Online</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['online']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En mission</p>
                <p class="text-2xl font-black text-purple-600">{{ number_format($stats['busy']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En pause</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['on_break']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Offline</p>
                <p class="text-2xl font-black text-slate-500">{{ number_format($stats['offline']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Stale (5min+)</p>
                <p class="text-2xl font-black text-rose-600">{{ number_format($stats['stale_candidates']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Min online aujourd'hui</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_online_minutes_today']) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche nom / email..." class="rounded-lg border-slate-300 text-sm flex-1">
                <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="online">Online</option>
                    <option value="busy">En mission</option>
                    <option value="on_break">En pause</option>
                    <option value="offline">Offline</option>
                </select>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Provider</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2">Position</th>
                        <th class="px-3 py-2">Rayon dispo</th>
                        <th class="px-3 py-2">Dernier ping</th>
                        <th class="px-3 py-2">Online aujourd'hui</th>
                        <th class="px-3 py-2">Last online</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php
                            $color = ['online'=>'emerald','busy'=>'purple','on_break'=>'amber','offline'=>'slate'][$r->status] ?? 'slate';
                            $isStale = $r->heartbeat_at && $r->heartbeat_at->lt(now()->subMinutes(5));
                        @endphp
                        <tr class="border-t hover:bg-slate-50 {{ $isStale ? 'bg-rose-50' : '' }}">
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->provider?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $r->provider?->email }}</p>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $r->status }}</span>
                                @if ($isStale && $r->status !== 'offline')
                                    <p class="text-xs text-rose-600 mt-1">stale heartbeat</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">
                                @if ($r->current_lat !== null && $r->current_lng !== null)
                                    {{ number_format((float)$r->current_lat, 4) }}, {{ number_format((float)$r->current_lng, 4) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $r->available_radius_km ? $r->available_radius_km . ' km' : '—' }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $r->heartbeat_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">{{ (int) $r->online_minutes_today }} min</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $r->last_online_at?->format('d/m H:i') ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($r->status !== 'offline')
                                    <button wire:click="forceOffline({{ $r->provider_user_id }})"
                                            wire:confirm="Forcer ce provider offline ?"
                                            class="text-rose-600 hover:underline text-xs font-semibold">Force offline</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-400">Aucun provider tracké.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>
