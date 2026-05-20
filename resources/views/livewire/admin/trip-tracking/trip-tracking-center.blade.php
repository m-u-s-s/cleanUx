<div class="py-6" wire:poll.10s>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Tracking GPS missions</p>
                <h1 class="text-2xl font-black text-slate-900">Trip Tracking</h1>
                <p class="text-sm text-slate-500">Sessions GPS provider→client en temps réel + historique replay.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Actives now</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['active_now']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En route</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($stats['enroute']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Arrivés</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['arrived']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En mission</p>
                <p class="text-2xl font-black text-purple-600">{{ number_format($stats['in_mission']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Historique</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_history']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b">
            <button wire:click="setTab('live')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'live' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Live <span class="inline-block ml-1 rounded-full bg-emerald-100 text-emerald-700 px-2 py-0.5 text-xs">{{ $stats['active_now'] }}</span>
            </button>
            <button wire:click="setTab('history')" class="px-4 py-2 text-sm font-semibold {{ $tab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-slate-500' }}">
                Historique
            </button>
        </div>

        @if ($tab === 'live')
            <div class="rounded-2xl border bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Booking</th>
                            <th class="px-3 py-2">Statut</th>
                            <th class="px-3 py-2">ETA</th>
                            <th class="px-3 py-2">Distance</th>
                            <th class="px-3 py-2">Pings</th>
                            <th class="px-3 py-2">Dernier ping</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($liveSessions as $s)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ Str::limit($s->code, 18) }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $s->provider?->name }}</td>
                                <td class="px-3 py-2 text-xs">#{{ $s->booking_id }}</td>
                                <td class="px-3 py-2">
                                    @php $color = ['enroute'=>'indigo','arrived'=>'amber','in_mission'=>'purple','ended'=>'emerald','cancelled'=>'rose'][$s->status] ?? 'slate'; @endphp
                                    <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $s->status }}</span>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">
                                    @if ($s->current_eta_seconds !== null)
                                        {{ (int) ceil($s->current_eta_seconds / 60) }} min
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs">{{ number_format($s->total_distance_m / 1000, 2) }} km</td>
                                <td class="px-3 py-2 text-xs">{{ number_format($s->points_count) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $s->last_ping_at?->diffForHumans() ?? '—' }}</td>
                                <td class="px-3 py-2 text-right space-x-1">
                                    <button wire:click="openSession({{ $s->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Détail</button>
                                    <button wire:click="cancelSession({{ $s->id }})" wire:confirm="Annuler cette session ?" class="text-rose-600 hover:underline text-xs font-semibold">Annuler</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-8 text-center text-slate-400">Aucune session active.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($tab === 'history')
            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche code / provider..." class="rounded-lg border-slate-300 text-sm flex-1">
                    <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                        <option value="">Tous</option>
                        <option value="ended">Ended</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Provider</th>
                            <th class="px-3 py-2">Booking</th>
                            <th class="px-3 py-2">Statut</th>
                            <th class="px-3 py-2">Distance</th>
                            <th class="px-3 py-2">Pings</th>
                            <th class="px-3 py-2">Durée</th>
                            <th class="px-3 py-2">Fin</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historySessions as $s)
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 font-mono text-xs">{{ Str::limit($s->code, 18) }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $s->provider?->name }}</td>
                                <td class="px-3 py-2 text-xs">#{{ $s->booking_id }}</td>
                                <td class="px-3 py-2">
                                    @php $color = ['ended'=>'emerald','cancelled'=>'rose'][$s->status] ?? 'slate'; @endphp
                                    <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $s->status }}</span>
                                </td>
                                <td class="px-3 py-2 text-xs">{{ number_format($s->total_distance_m / 1000, 2) }} km</td>
                                <td class="px-3 py-2 text-xs">{{ number_format($s->points_count) }}</td>
                                <td class="px-3 py-2 text-xs">{{ $s->started_at && $s->ended_at ? $s->started_at->diffInMinutes($s->ended_at) . ' min' : '—' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $s->ended_at?->format('d/m H:i') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="openSession({{ $s->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Replay</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-8 text-center text-slate-400">Aucun historique.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $historySessions->links() }}</div>
            </div>
        @endif

        @if ($selectedSession)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <div class="p-6 border-b flex justify-between items-start">
                        <div>
                            <p class="text-xs uppercase font-bold text-indigo-600">Session #{{ $selectedSession->id }}</p>
                            <h2 class="text-lg font-bold text-slate-900">{{ $selectedSession->provider?->name }} → Booking #{{ $selectedSession->booking_id }}</h2>
                            <p class="text-xs text-slate-500 mt-1">{{ $selectedSession->code }}</p>
                        </div>
                        <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">×</button>
                    </div>

                    <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Statut</p>
                            <p class="text-sm font-bold">{{ $selectedSession->status }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Distance totale</p>
                            <p class="text-sm font-bold">{{ number_format($selectedSession->total_distance_m / 1000, 2) }} km</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">Points GPS</p>
                            <p class="text-sm font-bold">{{ number_format($selectedSession->points_count) }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase font-bold text-slate-500">ETA actuel</p>
                            <p class="text-sm font-bold">
                                @if ($selectedSession->current_eta_seconds !== null)
                                    {{ (int) ceil($selectedSession->current_eta_seconds / 60) }} min
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="p-6 border-t">
                        <p class="text-xs uppercase font-bold text-slate-500 mb-2">Trail GPS ({{ $selectedPoints->count() }} points)</p>
                        @if ($selectedPoints->isEmpty())
                            <p class="text-sm text-slate-400 text-center py-6">Aucun point enregistré.</p>
                        @else
                            <div class="rounded-lg bg-slate-50 p-3 max-h-72 overflow-y-auto">
                                <table class="w-full text-xs">
                                    <thead class="text-slate-500">
                                        <tr>
                                            <th class="text-left py-1">Time</th>
                                            <th class="text-left py-1">Lat</th>
                                            <th class="text-left py-1">Lng</th>
                                            <th class="text-left py-1">ETA</th>
                                            <th class="text-left py-1">Dist dest</th>
                                            <th class="text-left py-1">Speed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($selectedPoints as $p)
                                            <tr class="border-t border-slate-200">
                                                <td class="py-1 text-slate-500">{{ $p->recorded_at?->format('H:i:s') }}</td>
                                                <td class="py-1 font-mono">{{ number_format($p->lat, 5) }}</td>
                                                <td class="py-1 font-mono">{{ number_format($p->lng, 5) }}</td>
                                                <td class="py-1">{{ $p->eta_seconds !== null ? (int) ceil($p->eta_seconds / 60) . 'min' : '—' }}</td>
                                                <td class="py-1">{{ $p->distance_to_dest_m !== null ? number_format($p->distance_to_dest_m / 1000, 2) . ' km' : '—' }}</td>
                                                <td class="py-1">{{ $p->speed_mps !== null ? number_format($p->speed_mps * 3.6, 1) . ' km/h' : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
