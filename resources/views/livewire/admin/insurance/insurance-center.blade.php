<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Insurance v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Insurance / Claims</h1>
                <p class="text-sm text-slate-500">
                    Provider : <code class="font-mono">{{ config('insurance.default_provider') }}</code>
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Plans actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['plans_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Policies actives</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['policies_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Claims ouverts</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['claims_open']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Payés 30j</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['claims_paid_30d']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['claims' => 'Claims', 'policies' => 'Policies', 'plans' => 'Plans'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'claims')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Claimant</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-right">Montant</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $c->filed_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->claimant?->email }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->incident_type }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($c->amount_claimed_cents / 100, 2) }} €</td>
                                <td class="px-4 py-2"><span class="text-xs font-semibold">{{ $c->status }}</span></td>
                                <td class="px-4 py-2 text-right text-xs">
                                    @if($c->isOpen())
                                        <button wire:click="setClaimStatus({{ $c->id }}, 'accepted')" class="text-emerald-600 hover:underline mr-2">Accepter</button>
                                        <button wire:click="setClaimStatus({{ $c->id }}, 'rejected')" class="text-red-600 hover:underline mr-2">Rejeter</button>
                                    @elseif($c->status === 'accepted')
                                        <button wire:click="setClaimStatus({{ $c->id }}, 'paid')" class="text-indigo-600 hover:underline">Marquer payé</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun claim.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'policies')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date achat</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Plan</th>
                            <th class="px-4 py-2 text-right">Prime</th>
                            <th class="px-4 py-2 text-right">Coverage</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Provider</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $p)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $p->purchased_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->user?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $p->plan?->name }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($p->premium_cents / 100, 2) }} €</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($p->coverage_amount_cents / 100, 0) }} €</td>
                                <td class="px-4 py-2"><span class="text-xs font-semibold">{{ $p->status }}</span></td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $p->external_provider }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune policy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-right">Coverage</th>
                            <th class="px-4 py-2 text-right">Prime base</th>
                            <th class="px-4 py-2 text-right">% prime</th>
                            <th class="px-4 py-2 text-left">Actif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $pl)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $pl->code }}</td>
                                <td class="px-4 py-2 text-xs font-semibold">{{ $pl->name }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($pl->coverage_amount_cents / 100, 0) }} €</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($pl->premium_base_cents / 100, 2) }} €</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($pl->premium_percent, 2) }} %</td>
                                <td class="px-4 py-2 text-xs">{{ $pl->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun plan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
