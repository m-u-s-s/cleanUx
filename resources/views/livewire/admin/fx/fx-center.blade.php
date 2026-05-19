<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">FX v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Multi-currency / FX</h1>
                <p class="text-sm text-slate-500">
                    Provider : <code class="font-mono">{{ config('fx.default_provider') }}</code> |
                    Base : <code class="font-mono">{{ config('fx.base_currency') }}</code>
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="refreshAll" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Refresh rates</button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Devises actives</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['currencies_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Rates total</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['rates_total']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Fallback 24h</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['fallback_used_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Conversions 7j</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['conversions_7d']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['rates' => 'Rates ledger', 'conversions' => 'Conversions', 'currencies' => 'Devises'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'rates')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Fetched</th>
                            <th class="px-4 py-2 text-left">Pair</th>
                            <th class="px-4 py-2 text-right">Rate</th>
                            <th class="px-4 py-2 text-left">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $r)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $r->fetched_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $r->base_currency }} → {{ $r->quote_currency }}</td>
                                <td class="px-4 py-2 text-right text-xs font-mono">{{ number_format((float) $r->rate, 6) }}</td>
                                <td class="px-4 py-2 text-xs">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-amber-100 text-amber-800' => $r->source === 'fallback',
                                        'bg-emerald-100 text-emerald-800' => in_array($r->source, ['ecb', 'openexchange']),
                                        'bg-slate-100 text-slate-800' => in_array($r->source, ['mock', 'manual']),
                                    ])>{{ $r->source }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Aucun rate.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'conversions')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-right">Source</th>
                            <th class="px-4 py-2 text-right">Target</th>
                            <th class="px-4 py-2 text-right">Rate</th>
                            <th class="px-4 py-2 text-right">Fee %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $c)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $c->converted_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $c->user?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($c->source_amount_cents / 100, 2) }} {{ $c->source_currency }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($c->target_amount_cents / 100, 2) }} {{ $c->target_currency }}</td>
                                <td class="px-4 py-2 text-right text-xs font-mono">{{ number_format((float) $c->rate_used, 6) }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format((float) $c->fee_percent, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune conversion.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Symbole</th>
                            <th class="px-4 py-2 text-right">Décimales</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $cur)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $cur->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cur->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cur->symbol }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ $cur->decimals }}</td>
                                <td class="px-4 py-2 text-xs">{{ $cur->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucune devise.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
