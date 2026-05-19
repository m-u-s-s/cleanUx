<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Webhooks v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Webhooks B2B</h1>
                <p class="text-sm text-slate-500">Endpoints + events + deliveries (HMAC SHA256 + retry exponential)</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Endpoints actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['endpoints_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Endpoints suspendus</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['endpoints_suspended']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Deliveries en attente</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['deliveries_pending']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Dead-letter</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['deliveries_dead']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['endpoints' => 'Endpoints', 'events' => 'Events', 'deliveries' => 'Deliveries'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'endpoints')
            <div class="rounded-2xl border bg-white p-4 shadow-sm space-y-3">
                <p class="text-sm font-bold text-slate-900">Nouveau endpoint</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <input type="text" wire:model="newName" placeholder="Nom (Acme Prod)" class="rounded-xl border-gray-300 text-sm" />
                    <input type="url" wire:model="newUrl" placeholder="https://api.client.com/webhooks" class="rounded-xl border-gray-300 text-sm md:col-span-2" />
                </div>
                <div>
                    <p class="text-xs font-bold uppercase text-slate-500 mb-1">Events</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allowedEvents as $ev)
                            <label class="flex items-center gap-1 text-xs">
                                <input type="checkbox" wire:model="newEventCodes" value="{{ $ev }}" />
                                <span class="font-mono">{{ $ev }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button wire:click="createEndpoint" class="rounded-xl bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700">
                    Créer endpoint
                </button>
            </div>
        @elseif($tab === 'deliveries')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="pending">Pending</option>
                    <option value="in_flight">In flight</option>
                    <option value="delivered">Delivered</option>
                    <option value="failed">Failed</option>
                    <option value="dead">Dead</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'endpoints')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">URL</th>
                            <th class="px-4 py-2 text-left">Subs</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Echecs consécutifs</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $ep)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $ep->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $ep->name }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ \Illuminate\Support\Str::limit($ep->url, 50) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $ep->subscriptions_count }}</td>
                                <td class="px-4 py-2 text-xs">
                                    @if($ep->is_suspended)
                                        <span class="text-amber-600">⊘ suspendu</span>
                                    @elseif($ep->is_active)
                                        <span class="text-emerald-600">✓ actif</span>
                                    @else
                                        <span class="text-slate-400">— inactif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $ep->consecutive_failures }}</td>
                                <td class="px-4 py-2 text-right text-xs space-x-2">
                                    <button wire:click="sendTest({{ $ep->id }})" class="text-indigo-600 hover:underline">Test</button>
                                    <button wire:click="rotateSecret({{ $ep->id }})" class="text-slate-600 hover:underline">Rotate</button>
                                    <button wire:click="toggleSuspend({{ $ep->id }})" class="text-amber-600 hover:underline">
                                        {{ $ep->is_suspended ? 'Reprendre' : 'Suspendre' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun endpoint.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'events')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Event ID</th>
                            <th class="px-4 py-2 text-left">Event code</th>
                            <th class="px-4 py-2 text-left">Source</th>
                            <th class="px-4 py-2 text-left">Occurred</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->event_id }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->event_code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->source_type ?? '—' }}{{ $e->source_id ? ' #' . $e->source_id : '' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($e->occurred_at)->format('d/m H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aucun event.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Event</th>
                            <th class="px-4 py-2 text-left">Endpoint</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Attempt</th>
                            <th class="px-4 py-2 text-left">HTTP</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $d)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($d->last_attempted_at ?? $d->created_at)->format('d/m H:i:s') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $d->event?->event_code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->endpoint?->name }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $d->status === 'delivered',
                                        'bg-amber-100 text-amber-800' => in_array($d->status, ['pending', 'in_flight', 'failed']),
                                        'bg-red-100 text-red-800' => $d->status === 'dead',
                                        'bg-slate-100 text-slate-800' => $d->status === 'cancelled',
                                    ])>{{ $d->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $d->attempt }}/{{ $d->max_attempts }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->last_response_status ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(in_array($d->status, ['failed', 'dead']))
                                        <button wire:click="replay({{ $d->id }})" class="text-indigo-600 hover:underline">Replay</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune delivery.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
