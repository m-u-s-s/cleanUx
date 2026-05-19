<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Risk v2</p>
                <h1 class="text-2xl font-black text-slate-900">Centre Risk / Anti-fraude</h1>
                <p class="text-sm text-slate-500">
                    Seuils : review ≥ <code>{{ config('risk.thresholds.review') }}</code>,
                    block ≥ <code>{{ config('risk.thresholds.block') }}</code>
                </p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Évaluations 24h</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($kpis['evaluations_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Blocked 24h</p>
                <p class="text-2xl font-black text-red-600">{{ number_format($kpis['blocked_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Review 24h</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($kpis['review_24h']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Holds actifs</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($kpis['active_holds']) }}</p>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200">
            <button wire:click="$set('tab', 'pending')"
                    @class([
                        'px-4 py-2 text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $tab === 'pending',
                        'text-slate-500 hover:text-slate-900' => $tab !== 'pending',
                    ])>Holds en attente</button>
            <button wire:click="$set('tab', 'history')"
                    @class([
                        'px-4 py-2 text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $tab === 'history',
                        'text-slate-500 hover:text-slate-900' => $tab !== 'history',
                    ])>Historique évaluations</button>
        </div>

        @if($tab === 'pending')
            <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Contexte</th>
                            <th class="px-4 py-2 text-right">Score</th>
                            <th class="px-4 py-2 text-left">Raison</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $h)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $h->created_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $h->user?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $h->evaluation?->context }}</td>
                                <td class="px-4 py-2 text-right text-xs font-bold">{{ $h->evaluation?->score }}</td>
                                <td class="px-4 py-2 text-xs text-slate-700 max-w-md truncate" title="{{ $h->reason }}">{{ $h->reason }}</td>
                                <td class="px-4 py-2 text-right text-xs">
                                    <button wire:click="approve({{ $h->id }})" class="font-semibold text-emerald-600 hover:underline mr-2">Approuver</button>
                                    <button wire:click="reject({{ $h->id }})" class="font-semibold text-red-600 hover:underline">Rejeter</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun hold en attente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="email, name..." class="flex-1 rounded-xl border-gray-300 text-sm" />
                <select wire:model.live="filterDecision" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Toutes décisions</option>
                    <option value="allow">Allow</option>
                    <option value="review">Review</option>
                    <option value="block">Block</option>
                </select>
                <select wire:model.live="filterContext" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous contextes</option>
                    <option value="booking_create">Booking create</option>
                    <option value="payment_attempt">Payment attempt</option>
                    <option value="login">Login</option>
                    <option value="signup">Signup</option>
                </select>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Contexte</th>
                            <th class="px-4 py-2 text-right">Score</th>
                            <th class="px-4 py-2 text-left">Décision</th>
                            <th class="px-4 py-2 text-left">Raison</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($items as $e)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $e->evaluated_at?->format('d/m H:i') }}</td>
                                <td class="px-4 py-2 text-xs">{{ $e->user?->email ?? '—' }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ $e->context }}</td>
                                <td class="px-4 py-2 text-right text-xs font-bold">{{ $e->score }}</td>
                                <td class="px-4 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $e->decision === 'allow',
                                        'bg-amber-100 text-amber-800' => $e->decision === 'review',
                                        'bg-red-100 text-red-800' => $e->decision === 'block',
                                    ])>{{ $e->decision }}</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-700 max-w-md truncate" title="{{ $e->reason }}">{{ $e->reason }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucune évaluation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3">{{ $items->links() }}</div>
            </div>
        @endif
    </div>
</div>
