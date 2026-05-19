<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Subscriptions v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Abonnements & Cycles</h1>
                <p class="text-sm text-slate-500">Plans récurrents + cycles + facturation Stripe/mock</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Plans actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['plans_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Abos actifs</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['subs_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Past due</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['subs_past_due']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Cycles failed</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['cycles_failed']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total facturé</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['total_billed_cents'] / 100, 2, ',', ' ') }} €</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['plans' => 'Plans', 'subscriptions' => 'Abonnements', 'cycles' => 'Cycles'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'subscriptions')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="trialing">Trialing</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="past_due">Past due</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
        @elseif($tab === 'cycles')
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="filterCycleStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="pending">Pending</option>
                    <option value="invoiced">Invoiced</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                    <option value="skipped">Skipped</option>
                </select>
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'plans')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Période</th>
                            <th class="px-4 py-2 text-left">Prix</th>
                            <th class="px-4 py-2 text-left">Inclus</th>
                            <th class="px-4 py-2 text-left">Trial</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->billing_period }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->priceFormatted() }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->included_units_per_cycle }} {{ $p->included_unit_type }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->trial_days }}j</td>
                                <td class="px-4 py-2 text-xs">{{ $p->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun plan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'subscriptions')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Plan</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Cycle courant</th>
                            <th class="px-4 py-2 text-left">Facturé</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($s->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->user?->email }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->plan?->name }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $s->status === 'active',
                                        'bg-blue-100 text-blue-800' => $s->status === 'trialing',
                                        'bg-amber-100 text-amber-800' => in_array($s->status, ['paused', 'past_due']),
                                        'bg-red-100 text-red-800' => $s->status === 'cancelled',
                                        'bg-slate-100 text-slate-800' => $s->status === 'expired',
                                    ])>{{ $s->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $s->billing_cycle_count }}</td>
                                <td class="px-4 py-2 text-xs">{{ number_format($s->total_billed_cents / 100, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(! in_array($s->status, ['cancelled', 'expired']))
                                        <button wire:click="forceCancel({{ $s->id }})" class="text-red-600 hover:underline"
                                            onclick="return confirm('Cancel immédiatement ?')">Force cancel</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucune subscription.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Sub</th>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Période</th>
                            <th class="px-4 py-2 text-left">Montant</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Erreur</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($c->created_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $c->subscription?->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->cycle_number }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($c->period_start)->format('d/m') }} → {{ optional($c->period_end)->format('d/m') }}</td>
                                <td class="px-4 py-2 text-xs">{{ number_format($c->planned_amount_cents / 100, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $c->billing_status === 'paid',
                                        'bg-amber-100 text-amber-800' => in_array($c->billing_status, ['pending', 'invoiced']),
                                        'bg-red-100 text-red-800' => $c->billing_status === 'failed',
                                        'bg-slate-100 text-slate-800' => $c->billing_status === 'skipped',
                                    ])>{{ $c->billing_status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-red-600">{{ \Illuminate\Support\Str::limit($c->last_error, 40) }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if(in_array($c->billing_status, ['pending', 'failed']))
                                        <button wire:click="retryBilling({{ $c->id }})" class="text-indigo-600 hover:underline">Retry</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Aucun cycle.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
