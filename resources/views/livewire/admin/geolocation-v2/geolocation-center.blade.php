<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Geolocation v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Géolocalisation</h1>
                <p class="text-sm text-slate-500">Autocomplete + geocoding + distance — provider <span class="font-mono">{{ $kpis['provider'] }}</span></p>
            </div>
            <div class="flex gap-2">
                <button wire:click="purgeCache" class="rounded-xl border bg-amber-50 text-amber-800 px-4 py-2 text-sm font-semibold hover:bg-amber-100">
                    Purger cache expiré
                </button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Lookups cachés</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['lookups_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Géocodages cachés</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['geocodings_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Distances cachées</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['distances_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Fallback haversine</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['haversine_fallback_count']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['lookups' => 'Autocomplete', 'geocodings' => 'Géocodages', 'distances' => 'Distances'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'lookups')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Query</th>
                            <th class="px-4 py-2 text-left">Pays</th>
                            <th class="px-4 py-2 text-left">Résultats</th>
                            <th class="px-4 py-2 text-left">Expire</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $l)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($l->queried_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $l->provider }}</td>
                                <td class="px-4 py-2 text-xs">{{ $l->query }}</td>
                                <td class="px-4 py-2 text-xs">{{ $l->country_code ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $l->result_count }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($l->expires_at)->format('d/m H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun lookup.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'geocodings')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Adresse</th>
                            <th class="px-4 py-2 text-left">Lat/Lng</th>
                            <th class="px-4 py-2 text-left">Code postal</th>
                            <th class="px-4 py-2 text-left">Pays</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $g)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($g->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $g->provider }}</td>
                                <td class="px-4 py-2 text-xs">{{ \Illuminate\Support\Str::limit($g->formatted_address ?: $g->address_input, 60) }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ number_format($g->latitude, 4) }} / {{ number_format($g->longitude, 4) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $g->postal_code ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $g->country_code ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun géocodage.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                            <th class="px-4 py-2 text-left">Mode</th>
                            <th class="px-4 py-2 text-left">Origine</th>
                            <th class="px-4 py-2 text-left">Destination</th>
                            <th class="px-4 py-2 text-left">Distance</th>
                            <th class="px-4 py-2 text-left">Durée</th>
                            <th class="px-4 py-2 text-left">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($d->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $d->provider }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->mode }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ number_format($d->origin_lat, 3) }} / {{ number_format($d->origin_lng, 3) }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ number_format($d->dest_lat, 3) }} / {{ number_format($d->dest_lng, 3) }}</td>
                                <td class="px-4 py-2 text-xs">{{ number_format($d->distance_meters / 1000, 1) }} km</td>
                                <td class="px-4 py-2 text-xs">{{ $d->duration_seconds ? ceil($d->duration_seconds / 60) . ' min' : '—' }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($d->is_fallback_haversine)
                                        <span class="text-amber-600">haversine</span>
                                    @else
                                        <span class="text-emerald-600">provider</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucune distance.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
