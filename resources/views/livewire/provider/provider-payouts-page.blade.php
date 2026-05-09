<div class="mx-auto max-w-5xl px-4 py-6 space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Mes versements</h1>
        <p class="mt-1 text-sm text-slate-500">
            Historique de tes paiements et versements Stripe Connect.
        </p>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Ce mois — payé</div>
            <div class="mt-1 text-2xl font-bold text-emerald-700">
                {{ number_format($summary['this_month_paid'], 2, ',', ' ') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">
                + {{ number_format($summary['this_month_pending'], 2, ',', ' ') }} € en attente
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Mois dernier — payé</div>
            <div class="mt-1 text-2xl font-bold text-slate-700">
                {{ number_format($summary['last_month_paid'], 2, ',', ' ') }} €
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Total payé</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">
                {{ number_format($summary['all_time_paid'], 2, ',', ' ') }} €
            </div>
            <div class="mt-1 text-xs text-slate-500">
                + {{ number_format($summary['all_time_pending'], 2, ',', ' ') }} € en attente
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <div>
                <label class="block text-xs font-medium text-slate-700">Statut</label>
                <select wire:model.live="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">— Tous —</option>
                    <option value="pending">En attente</option>
                    <option value="processing">En cours</option>
                    <option value="paid">Payé</option>
                    <option value="failed">Échec</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Du</label>
                <input type="date" wire:model.live="fromDate" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700">Au</label>
                <input type="date" wire:model.live="toDate" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>
            <div class="flex items-end">
                <button wire:click="clearFilters"
                        class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>

    {{-- Payouts table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-200 bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Date</th>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Mission</th>
                    <th class="px-4 py-2 text-right font-semibold text-slate-700">Montant</th>
                    <th class="px-4 py-2 text-center font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-2 text-left font-semibold text-slate-700">Réf. Stripe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payouts as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5 text-slate-700">
                            {{ $p->created_at?->locale('fr')->isoFormat('D MMM YYYY') }}
                        </td>
                        <td class="px-4 py-2.5">
                            @php
                                $ref = $p->metadata['booking_reference'] ?? null;
                                $missionId = $p->metadata['mission_id'] ?? null;
                            @endphp
                            @if ($ref)
                                <span class="font-mono text-xs">{{ $ref }}</span>
                            @elseif ($missionId)
                                <span class="text-xs text-slate-500">Mission #{{ $missionId }}</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold text-slate-900">
                            {{ number_format((float) $p->amount, 2, ',', ' ') }} {{ $p->currency }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @php
                                $badgeClass = match ($p->status) {
                                    'paid'       => 'bg-emerald-100 text-emerald-700',
                                    'pending'    => 'bg-amber-100 text-amber-700',
                                    'processing' => 'bg-blue-100 text-blue-700',
                                    'failed'     => 'bg-red-100 text-red-700',
                                    default      => 'bg-slate-100 text-slate-700',
                                };
                                $statusLabel = match ($p->status) {
                                    'paid' => 'Payé', 'pending' => 'En attente',
                                    'processing' => 'En cours', 'failed' => 'Échec',
                                    default => $p->status,
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                            {{ $p->provider_payout_id ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                            Aucun versement à afficher.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($payouts->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $payouts->links() }}
            </div>
        @endif
    </div>
</div>
