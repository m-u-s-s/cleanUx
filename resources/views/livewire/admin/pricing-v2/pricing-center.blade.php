<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Pricing v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Service Catalog & Pricing</h1>
                <p class="text-sm text-slate-500">Service catalog + pricing rules DSL + A/B experiments</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Services actifs</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['services_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Rules actives</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($kpis['rules_active']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Quotes 7j</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['quotes_7d']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Experiments en cours</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['experiments_running']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            @foreach(['services' => 'Services', 'rules' => 'Rules', 'quotes' => 'Quotes', 'experiments' => 'Experiments A/B'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        @class([
                            'px-4 py-2 text-sm font-semibold',
                            'border-b-2 border-indigo-600 text-indigo-700' => $tab === $key,
                            'text-slate-500 hover:text-slate-900' => $tab !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </div>

        @if($tab === 'services' || $tab === 'rules')
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Code / nom..." class="w-full rounded-xl border-gray-300 text-sm" />
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
            @if($tab === 'services')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Trade</th>
                            <th class="px-4 py-2 text-right">Base</th>
                            <th class="px-4 py-2 text-left">Unit</th>
                            <th class="px-4 py-2 text-right">Version</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $s)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $s->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->trade_code ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($s->base_price_cents / 100, 2) }} {{ $s->currency }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->unit }}</td>
                                <td class="px-4 py-2 text-right text-xs">v{{ $s->version }}</td>
                                <td class="px-4 py-2 text-xs">{{ $s->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun service.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'rules')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">Trade</th>
                            <th class="px-4 py-2 text-right">Priority</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $r)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $r->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->name }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->service_code ?? '*' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->trade_code ?? '*' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ $r->priority }}</td>
                                <td class="px-4 py-2 text-xs">{{ $r->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune rule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($tab === 'quotes')
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Service</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-right">Base</th>
                            <th class="px-4 py-2 text-right">Computed</th>
                            <th class="px-4 py-2 text-left">Variant</th>
                            <th class="px-4 py-2 text-right">Rules</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $q)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $q->quoted_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $q->service_code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $q->user?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ number_format($q->base_price_cents / 100, 2) }}</td>
                                <td class="px-4 py-2 text-right text-xs font-bold">{{ number_format($q->computed_price_cents / 100, 2) }} {{ $q->currency }}</td>
                                <td class="px-4 py-2 text-xs">{{ $q->variant_label ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ count($q->applied_rules ?? []) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucun quote.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-left">Nom</th>
                            <th class="px-4 py-2 text-right">Variants</th>
                            <th class="px-4 py-2 text-left">Starts</th>
                            <th class="px-4 py-2 text-left">Ends</th>
                            <th class="px-4 py-2 text-left">Active</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->code }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->name }}</td>
                                <td class="px-4 py-2 text-right text-xs">{{ count($e->variants ?? []) }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $e->starts_at?->format('d/m') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $e->ends_at?->format('d/m') ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->is_active ? '✓' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun experiment.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
            <div class="p-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
