<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Mon portefeuille</p>
            <h1 class="text-3xl font-black text-slate-900">Solde et retraits</h1>
        </div>

        {{-- Balance card --}}
        <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-purple-700 p-8 text-white shadow-xl">
            <p class="text-sm font-bold uppercase opacity-80">Solde disponible</p>
            <p class="text-5xl font-black mt-2">
                {{ number_format($balance['available'], 2, ',', ' ') }}
                <span class="text-2xl font-semibold opacity-80">{{ $balance['currency'] }}</span>
            </p>

            <div class="grid grid-cols-2 gap-4 mt-6 pt-6 border-t border-white/20">
                <div>
                    <p class="text-xs uppercase opacity-80">En attente</p>
                    <p class="text-2xl font-bold">
                        {{ number_format($balance['pending'], 2, ',', ' ') }} {{ $balance['currency'] }}
                    </p>
                </div>
                <div>
                    <p class="text-xs uppercase opacity-80">Total</p>
                    <p class="text-2xl font-bold">
                        {{ number_format($balance['total'], 2, ',', ' ') }} {{ $balance['currency'] }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Withdraw --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">Demander un retrait</h2>
            <p class="text-sm text-slate-500 mt-1">
                Minimum {{ number_format($minWithdraw, 2, ',', ' ') }} {{ $balance['currency'] }}.
                Transfert vers votre compte Stripe Connect, 1-2 jours ouvrés.
            </p>

            <div class="flex flex-col md:flex-row gap-3 mt-4">
                <div class="flex-1">
                    <input type="number" step="0.01" min="{{ $minWithdraw }}"
                           wire:model="withdrawAmount"
                           placeholder="Montant en {{ $balance['currency'] }}"
                           class="w-full rounded-xl border-gray-300 text-sm" />
                    @error('withdrawAmount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <button wire:click="withdraw"
                        class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Retirer
                </button>
            </div>

            @if($withdrawError)
                <div class="mt-3 rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    {{ $withdrawError }}
                </div>
            @endif
            @if($withdrawSuccess)
                <div class="mt-3 rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-700">
                    {{ $withdrawSuccess }}
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Recent payouts --}}
            <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Derniers retraits</h2>
                <div class="mt-3 space-y-2">
                    @forelse($recentPayouts as $p)
                        <div class="flex items-center justify-between py-2 border-b last:border-0">
                            <div>
                                <p class="text-sm font-semibold">
                                    {{ number_format((float) $p->amount, 2, ',', ' ') }} {{ $p->currency }}
                                </p>
                                <p class="text-xs text-slate-500">{{ $p->created_at->format('d/m/Y') }}</p>
                            </div>
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800' => $p->status === 'paid',
                                'bg-amber-100 text-amber-800' => in_array($p->status, ['pending','processing']),
                                'bg-red-100 text-red-800' => $p->status === 'failed',
                            ])>{{ $p->status }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-6">Aucun retrait.</p>
                    @endforelse
                </div>
            </div>

            {{-- Transactions --}}
            <div class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Historique des transactions</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Type</th>
                                <th class="px-3 py-2 text-left">Description</th>
                                <th class="px-3 py-2 text-right">Montant</th>
                                <th class="px-3 py-2 text-left">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($transactions as $t)
                                <tr>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        {{ $t->occurred_at?->format('d/m/Y') }}
                                    </td>
                                    <td class="px-3 py-2 text-xs">
                                        <span class="font-mono">{{ $t->type }}</span>
                                    </td>
                                    <td class="px-3 py-2">{{ $t->description ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-bold {{ $t->direction === 'credit' ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $t->direction === 'credit' ? '+' : '-' }}{{ number_format((float) $t->amount, 2, ',', ' ') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-xs">{{ $t->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-3 py-10 text-center text-slate-400">
                                    Aucune transaction.
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $transactions->links() }}</div>
            </div>
        </div>
    </div>
</div>
