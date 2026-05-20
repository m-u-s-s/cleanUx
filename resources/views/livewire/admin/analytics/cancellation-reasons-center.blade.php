<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Analytics</p>
                <h1 class="text-2xl font-black text-slate-900">Raisons d'annulation</h1>
                <p class="text-sm text-slate-500">Pivot des annulations pour identifier les frictions.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="flex gap-2">
            @foreach (['7d' => '7 jours', '30d' => '30 jours', '90d' => '90 jours', 'all' => 'Tout'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Annulations</p>
                <p class="text-2xl font-black text-rose-600">{{ number_format($totalCancelled) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Total period</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($totalAll) }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Taux d'annulation</p>
                <p class="text-2xl font-black {{ $cancellationRate > 15 ? 'text-rose-600' : ($cancellationRate > 8 ? 'text-amber-600' : 'text-emerald-600') }}">
                    {{ $cancellationRate }}%
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-2xl border bg-white shadow-sm">
                <div class="p-4 border-b">
                    <h2 class="text-sm font-bold text-slate-900">Top raisons d'annulation</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Raison</th>
                            <th class="px-3 py-2 text-right">Count</th>
                            <th class="px-3 py-2 text-right">%</th>
                            <th class="px-3 py-2 text-right">Frais perçus</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $r)
                            @php $pct = $totalCancelled > 0 ? round(($r->count / $totalCancelled) * 100, 1) : 0; @endphp
                            <tr class="border-t hover:bg-slate-50">
                                <td class="px-3 py-2 text-sm">{{ \Illuminate\Support\Str::limit($r->cancellation_reason, 80) }}</td>
                                <td class="px-3 py-2 text-right font-bold">{{ number_format($r->count) }}</td>
                                <td class="px-3 py-2 text-right text-slate-600 text-xs">{{ $pct }}%</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ number_format(((int) $r->total_fee_cents) / 100, 2, ',', ' ') }} €</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-3 py-8 text-center text-slate-400">Aucune annulation sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border bg-white shadow-sm">
                <div class="p-4 border-b">
                    <h2 class="text-sm font-bold text-slate-900">Annulé par</h2>
                </div>
                <div class="p-4 space-y-2">
                    @forelse ($byCancelledBy as $b)
                        @php $pct = $totalCancelled > 0 ? round(($b->count / $totalCancelled) * 100, 1) : 0; @endphp
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="font-semibold">{{ $b->cancelled_by }}</span>
                                <span class="text-slate-500 text-xs">{{ number_format($b->count) }} ({{ $pct }}%)</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded mt-1">
                                <div class="bg-indigo-600 h-2 rounded" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-6">Donnée non disponible.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
