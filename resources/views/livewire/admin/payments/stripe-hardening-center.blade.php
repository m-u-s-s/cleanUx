<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Stripe v2 — Hardening</p>
                <h1 class="text-2xl font-black text-slate-900">Webhooks, réconciliation, échecs</h1>
                <p class="text-sm text-slate-500">
                    Surveillance temps-réel de l'intégration Stripe Connect : idempotence, retries, écarts.
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← Dashboard
            </a>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Events 24h</p>
                <p class="text-2xl font-black text-indigo-600">{{ $kpis['events_received_24h'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En échec</p>
                <p class="text-2xl font-black text-amber-600">{{ $kpis['events_failed'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Dead letter</p>
                <p class="text-2xl font-black text-red-600">{{ $kpis['events_dead_letter'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Dernière réconciliation</p>
                <p class="text-sm font-bold text-slate-700">
                    {{ optional($kpis['last_reconciliation']?->started_at)->diffForHumans() ?? 'Jamais' }}
                </p>
                @if($kpis['last_reconciliation'])
                    <p class="text-xs text-slate-500">
                        {{ $kpis['last_reconciliation']->mismatches_found }} écart(s)
                    </p>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-2 border-b">
            @foreach([
                'webhooks' => 'Webhooks',
                'failures' => 'Échecs',
                'reconciliation' => 'Réconciliation',
            ] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 text-sm font-semibold border-b-2 {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if($currentView === 'webhooks')
            <div class="flex gap-2">
                <select wire:model.live="statusFilter" class="rounded-xl border-slate-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="received">Reçus</option>
                    <option value="processing">En cours</option>
                    <option value="processed">Traités</option>
                    <option value="ignored">Ignorés</option>
                    <option value="failed">Échec</option>
                    <option value="dead_letter">Dead letter</option>
                </select>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Stripe ID</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Statut</th>
                            <th class="px-4 py-2 text-left">Tentatives</th>
                            <th class="px-4 py-2 text-left">Reçu</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 font-mono text-xs">{{ $e->stripe_event_id }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->type }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $e->status === 'processed',
                                        'bg-slate-100 text-slate-700' => in_array($e->status, ['received','processing','ignored']),
                                        'bg-amber-100 text-amber-800' => $e->status === 'failed',
                                        'bg-red-100 text-red-800' => $e->status === 'dead_letter',
                                    ])>{{ $e->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs">{{ $e->attempts }}/{{ $e->max_attempts }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ optional($e->received_at)->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    @if($e->canRetry() || $e->status === 'dead_letter')
                                        <button wire:click="retryEvent({{ $e->id }})"
                                                class="text-xs font-semibold text-indigo-600 hover:underline">Retry</button>
                                    @endif
                                    @if(! $e->isTerminal())
                                        <button wire:click="markIgnored({{ $e->id }})"
                                                class="text-xs font-semibold text-slate-500 hover:underline">Ignorer</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                Aucun event.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @elseif($currentView === 'failures')
            <div class="space-y-3">
                @forelse($items as $e)
                    <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs">{{ $e->stripe_event_id }}</span>
                                    <span class="inline-flex items-center rounded-full bg-red-200 px-2 py-0.5 text-xs font-bold text-red-900">
                                        {{ $e->status }}
                                    </span>
                                    <span class="text-xs text-slate-500">{{ $e->type }}</span>
                                </div>
                                <p class="text-sm text-red-800 mt-2">{{ $e->last_error }}</p>
                                <p class="text-xs text-slate-500 mt-1">
                                    {{ $e->attempts }}/{{ $e->max_attempts }} tentatives ·
                                    Prochain retry : {{ optional($e->next_retry_at)->diffForHumans() ?? 'jamais' }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <button wire:click="retryEvent({{ $e->id }})"
                                        class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                    Forcer retry
                                </button>
                                <button wire:click="markIgnored({{ $e->id }})"
                                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Ignorer
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                        Aucun event en échec.
                    </div>
                @endforelse
            </div>
            <div>{{ $items->links() }}</div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-bold text-slate-900">Lancer une réconciliation</h2>
                    <p class="text-xs text-slate-500">
                        Compare Stripe ↔ DB locale pour détecter les écarts (status booking, payouts orphelins...).
                    </p>

                    <div>
                        <label class="text-sm font-semibold text-slate-700">Scope</label>
                        <select wire:model="reconcileScope" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="all">Tout</option>
                            <option value="payment_intents">Payment intents</option>
                            <option value="payouts">Payouts</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Du</label>
                        <input type="date" wire:model="reconcileFromDate" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('reconcileFromDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">Au</label>
                        <input type="date" wire:model="reconcileToDate" class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        @error('reconcileToDate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button wire:click="runReconciliation"
                            class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Lancer
                    </button>
                </div>

                <div class="lg:col-span-2 rounded-2xl border bg-white shadow-sm overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Scope</th>
                                <th class="px-4 py-2 text-left">Période</th>
                                <th class="px-4 py-2 text-left">Items</th>
                                <th class="px-4 py-2 text-left">Écarts</th>
                                <th class="px-4 py-2 text-left">Critiques</th>
                                <th class="px-4 py-2 text-left">Statut</th>
                                <th class="px-4 py-2 text-left">Lancé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($items as $r)
                                <tr>
                                    <td class="px-4 py-2 font-semibold">{{ $r->scope }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        {{ $r->period_start?->format('d/m') }} → {{ $r->period_end?->format('d/m') }}
                                    </td>
                                    <td class="px-4 py-2">{{ $r->items_checked }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded-full {{ $r->mismatches_found > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }} px-2 py-0.5 text-xs font-bold">
                                            {{ $r->mismatches_found }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="rounded-full {{ $r->requires_attention > 0 ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600' }} px-2 py-0.5 text-xs font-bold">
                                            {{ $r->requires_attention }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs">{{ $r->status }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-500">
                                        {{ optional($r->started_at)->format('d/m H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                    Aucune réconciliation lancée — exécutez la première via le formulaire à gauche ou <code>php artisan stripe:reconcile</code>.
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-3">{{ $items->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
