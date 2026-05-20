<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Pourboires</p>
                <h1 class="text-2xl font-black text-slate-900">Centre des tips</h1>
                <p class="text-sm text-slate-500">Suivi pourboires post-mission, payout providers.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total tips</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_count']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">En attente</p>
                <p class="text-2xl font-black text-amber-600">{{ number_format($stats['pending_count']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Chargés</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($stats['charged_count']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Payés</p>
                <p class="text-2xl font-black text-emerald-600">{{ number_format($stats['paid_out_count']) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Volume €</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($stats['total_charged_cents'] / 100, 2, ',', ' ') }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Moyenne</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format(($stats['avg_tip_cents'] ?? 0) / 100, 2, ',', ' ') }} €</p>
            </div>
        </div>

        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche code / nom / email..." class="rounded-lg border-slate-300 text-sm flex-1">
                <select wire:model.live="statusFilter" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous statuts</option>
                    <option value="pending">En attente</option>
                    <option value="charged">Chargés</option>
                    <option value="paid_out">Payés</option>
                    <option value="failed">Failed</option>
                    <option value="cancelled">Annulés</option>
                </select>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Provider</th>
                        <th class="px-3 py-2">Mission</th>
                        <th class="px-3 py-2">Montant</th>
                        <th class="px-3 py-2">Statut</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tips as $t)
                        <tr class="border-t hover:bg-slate-50">
                            <td class="px-3 py-2 font-mono text-xs">{{ $t->code }}</td>
                            <td class="px-3 py-2">{{ $t->client?->name }} <span class="text-slate-400 text-xs">{{ $t->client?->email }}</span></td>
                            <td class="px-3 py-2">{{ $t->provider?->name }}</td>
                            <td class="px-3 py-2 text-xs">#{{ $t->booking_id }}</td>
                            <td class="px-3 py-2 font-bold">{{ $t->amountFormatted() }}</td>
                            <td class="px-3 py-2">
                                @php $color = ['pending'=>'amber','charged'=>'indigo','paid_out'=>'emerald','failed'=>'rose','cancelled'=>'slate','refunded'=>'orange'][$t->status] ?? 'slate'; @endphp
                                <span class="inline-block rounded-full bg-{{ $color }}-100 text-{{ $color }}-700 px-2 py-0.5 text-xs font-semibold">{{ $t->status }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $t->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-3 py-2 text-right space-x-1">
                                @if ($t->status === 'pending')
                                    <button wire:click="confirmTip({{ $t->id }})" class="text-emerald-600 hover:underline text-xs font-semibold">Charger</button>
                                    <button wire:click="markFailed({{ $t->id }})" class="text-rose-600 hover:underline text-xs font-semibold">Failed</button>
                                @elseif ($t->status === 'charged')
                                    <button wire:click="markPaidOut({{ $t->id }})" class="text-indigo-600 hover:underline text-xs font-semibold">Marquer payé</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-8 text-center text-slate-400">Aucun pourboire.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $tips->links() }}</div>
        </div>
    </div>
</div>
