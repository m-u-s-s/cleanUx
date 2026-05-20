<div class="py-6 max-w-6xl mx-auto px-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Mes revenus</p>
            <h1 class="text-2xl font-black text-slate-900">Earnings dashboard</h1>
        </div>
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl">
            @foreach (['today' => 'Aujourd\'hui', 'week' => 'Semaine', 'month' => 'Mois', 'year' => 'Année'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-white text-indigo-700 shadow' : 'text-slate-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-white border shadow-sm p-5">
            <p class="text-xs uppercase font-bold text-slate-500">Revenu total</p>
            <p class="text-3xl font-black text-slate-900 mt-1">
                {{ number_format($current['gross_cents'] / 100, 2, ',', ' ') }} €
            </p>
            @if ($delta !== null)
                <p class="text-xs font-semibold mt-2 {{ $delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $delta >= 0 ? '↑' : '↓' }} {{ abs($delta) }}% vs précédent
                </p>
            @endif
        </div>
        <div class="rounded-2xl bg-white border shadow-sm p-5">
            <p class="text-xs uppercase font-bold text-slate-500">Missions</p>
            <p class="text-3xl font-black text-indigo-700 mt-1">{{ number_format($current['missions_count']) }}</p>
            @if ($missionsDelta !== null)
                <p class="text-xs font-semibold mt-2 {{ $missionsDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $missionsDelta >= 0 ? '↑' : '↓' }} {{ abs($missionsDelta) }}%
                </p>
            @endif
        </div>
        <div class="rounded-2xl bg-white border shadow-sm p-5">
            <p class="text-xs uppercase font-bold text-slate-500">Pourboires</p>
            <p class="text-3xl font-black text-amber-600 mt-1">
                {{ number_format($current['tips_cents'] / 100, 2, ',', ' ') }} €
            </p>
        </div>
        <div class="rounded-2xl bg-white border shadow-sm p-5">
            <p class="text-xs uppercase font-bold text-slate-500">Versé wallet</p>
            <p class="text-3xl font-black text-emerald-600 mt-1">
                {{ number_format($current['wallet_paid_out_cents'] / 100, 2, ',', ' ') }} €
            </p>
        </div>
    </div>

    {{-- Chart --}}
    <div class="rounded-2xl bg-white border shadow-sm p-6 mb-6">
        <h2 class="text-sm font-bold text-slate-900 mb-4">Évolution sur la période</h2>
        @if (count($series) === 0)
            <p class="text-center text-slate-400 py-12">Aucune donnée sur cette période.</p>
        @else
            @php
                $maxAmount = max(array_column($series, 'amount_eur')) ?: 1;
            @endphp
            <div class="flex items-end gap-2 h-48">
                @foreach ($series as $point)
                    @php
                        $height = max(2, ($point['amount_eur'] / $maxAmount) * 180);
                    @endphp
                    <div class="flex-1 flex flex-col items-center gap-1 group">
                        <div class="text-xs font-semibold text-slate-700 opacity-0 group-hover:opacity-100 transition">
                            {{ number_format($point['amount_eur'], 0, ',', ' ') }}€
                        </div>
                        <div class="w-full bg-gradient-to-t from-indigo-500 to-purple-500 rounded-t" style="height: {{ $height }}px"></div>
                        <p class="text-xs text-slate-500">{{ $point['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Top trades + breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Top métiers</h2>
            @if (count($topTrades) === 0)
                <p class="text-center text-slate-400 py-6 text-sm">Pas assez de données.</p>
            @else
                <div class="space-y-3">
                    @foreach ($topTrades as $t)
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm">{{ $t['trade_name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $t['missions'] }} mission(s)</p>
                            </div>
                            <p class="font-bold text-indigo-700">{{ number_format($t['total_eur'], 2, ',', ' ') }} €</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <h2 class="text-sm font-bold text-slate-900 mb-4">Décomposition revenus</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-600">Missions</span>
                    <span class="font-bold">{{ number_format($current['mission_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-600">Pourboires</span>
                    <span class="font-bold text-amber-600">+{{ number_format($current['tips_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="border-t pt-2 mt-2 flex justify-between">
                    <span class="font-bold text-slate-900">Total brut</span>
                    <span class="font-black text-indigo-700">{{ number_format($current['gross_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex justify-between text-xs text-slate-500 mt-3">
                    <span>Wallet crédité (récap période)</span>
                    <span>{{ number_format($current['wallet_credited_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span>Wallet payé (transferts Stripe)</span>
                    <span>{{ number_format($current['wallet_paid_out_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
            </div>
            <a href="{{ route('employe.wallet') ?? '#' }}" class="block mt-4 text-center text-xs text-indigo-600 hover:underline font-semibold">
                Voir mon wallet détaillé →
            </a>
        </div>
    </div>
</div>
