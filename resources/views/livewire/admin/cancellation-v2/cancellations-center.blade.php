<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Cancellation v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Annulations / Remboursements</h1>
                <p class="text-sm text-slate-500">Policies + tiers + exempt reasons + override admin</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Annulations 7j</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['cancellations_7d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Fees collectés 7j</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['fees_collected_7d_cents'] / 100, 2) }} €</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Overrides 7j</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['overrides_7d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Policies actives</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['active_policies']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['recent' => 'Récentes', 'overrides' => 'Overrides', 'policies' => 'Policies'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'recent')
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="User email..." class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="filterActorRole" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous actors</option>
                    <option value="client">Client</option>
                    <option value="provider">Provider</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'recent')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Booking</th>
                            <th class="px-4 py-2 text-left">Actor</th>
                            <th class="px-4 py-2 text-left">Cancelled by</th>
                            <th class="px-4 py-2 text-left">Policy</th>
                            <th class="px-4 py-2 text-right">Fee</th>
                            <th class="px-4 py-2 text-right">Refund</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $c->cancelled_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $c->booking_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->actor_role }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->cancelledBy?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->policy?->code ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($c->fee_amount_cents / 100, 2) }} {{ $c->currency }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($c->refund_amount_cents / 100, 2) }} {{ $c->currency }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! $c->override_admin_user_id && $c->fee_amount_cents > 0)
                                        <button wire:click="override({{ $c->id }})" class="text-amber-600 hover:underline">Override (waive fee)</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucune annulation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'overrides')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Booking</th>
                            <th class="px-4 py-2 text-left">Admin</th>
                            <th class="px-4 py-2 text-left">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $c->cancelled_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">#{{ $c->booking_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->overriddenBy?->email }}</td>
                                <td class="px-4 py-2 text-xs max-w-md truncate" title="{{ $c->override_reason }}">{{ $c->override_reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aucun override.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Actor</th>
                            <th class="px-4 py-2 text-right">Tiers</th>
                            <th class="px-4 py-2 text-right">Version</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->actor_role }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ $p->tiers_count ?? 0 }}</td>
                                <td class="px-4 py-2 text-right text-xs">v{{ $p->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune policy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
