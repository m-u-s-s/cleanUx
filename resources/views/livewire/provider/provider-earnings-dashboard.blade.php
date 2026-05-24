<div class="py-8 max-w-6xl mx-auto px-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="ui-page-eyebrow !mt-0">Mes revenus</p>
            <h1 class="ui-page-title">Earnings dashboard</h1>
            <p class="ui-page-subtitle">Suivi de vos gains, missions et pourboires.</p>
        </div>
        <div class="flex gap-1 bg-slate-100 p-1 rounded-xl shrink-0">
            @foreach (['today' => "Aujourd'hui", 'week' => 'Semaine', 'month' => 'Mois', 'year' => 'Année'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $period === $key ? 'bg-white text-brand-700 shadow-soft-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft-sm p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs uppercase font-semibold tracking-wide text-slate-500">Revenu total</p>
                    <p class="mt-1.5 text-2xl font-bold text-slate-900">
                        {{ number_format($current['gross_cents'] / 100, 2, ',', ' ') }} €
                    </p>
                </div>
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-50 text-brand-700 shrink-0">
                    <x-ui.icon name="currency-euro" class="w-5 h-5" />
                </div>
            </div>
            @if ($delta !== null)
                <p class="mt-3 inline-flex items-center gap-1 text-xs font-semibold {{ $delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    <x-ui.icon name="{{ $delta >= 0 ? 'arrow-up' : 'arrow-down' }}" class="w-3 h-3" />
                    {{ abs($delta) }}% vs précédent
                </p>
            @endif
        </div>

        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft-sm p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs uppercase font-semibold tracking-wide text-slate-500">Missions</p>
                    <p class="mt-1.5 text-2xl font-bold text-slate-900">{{ number_format($current['missions_count']) }}</p>
                </div>
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-700 shrink-0">
                    <x-ui.icon name="briefcase" class="w-5 h-5" />
                </div>
            </div>
            @if ($missionsDelta !== null)
                <p class="mt-3 inline-flex items-center gap-1 text-xs font-semibold {{ $missionsDelta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    <x-ui.icon name="{{ $missionsDelta >= 0 ? 'arrow-up' : 'arrow-down' }}" class="w-3 h-3" />
                    {{ abs($missionsDelta) }}%
                </p>
            @endif
        </div>

        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft-sm p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs uppercase font-semibold tracking-wide text-slate-500">Pourboires</p>
                    <p class="mt-1.5 text-2xl font-bold text-amber-600">
                        {{ number_format($current['tips_cents'] / 100, 2, ',', ' ') }} €
                    </p>
                </div>
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-amber-50 text-amber-700 shrink-0">
                    <x-ui.icon name="gift" class="w-5 h-5" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft-sm p-5">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-xs uppercase font-semibold tracking-wide text-slate-500">Versé wallet</p>
                    <p class="mt-1.5 text-2xl font-bold text-emerald-600">
                        {{ number_format($current['wallet_paid_out_cents'] / 100, 2, ',', ' ') }} €
                    </p>
                </div>
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700 shrink-0">
                    <x-ui.icon name="wallet" class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900">
                <x-ui.icon name="chart-bar" class="w-4 h-4 text-brand-600" />
                Évolution sur la période
            </h2>
        </div>

        @if (count($series) === 0)
            <div class="text-center py-12">
                <div class="grid h-10 w-10 mx-auto place-items-center rounded-xl bg-slate-100 text-slate-400 mb-2">
                    <x-ui.icon name="chart-bar" class="w-5 h-5" />
                </div>
                <p class="text-sm text-slate-500">Aucune donnée sur cette période.</p>
            </div>
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
                        <div class="text-[10px] font-semibold text-slate-700 opacity-0 group-hover:opacity-100 transition">
                            {{ number_format($point['amount_eur'], 0, ',', ' ') }}€
                        </div>
                        <div class="w-full bg-gradient-to-t from-brand-600 to-brand-400 rounded-t-md transition hover:from-brand-700 hover:to-brand-500" style="height: {{ $height }}px"></div>
                        <p class="text-[10px] text-slate-500">{{ $point['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Top trades + breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6">
            <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900 mb-4">
                <x-ui.icon name="arrow-trending-up" class="w-4 h-4 text-brand-600" />
                Top métiers
            </h2>
            @if (count($topTrades) === 0)
                <p class="text-center text-slate-400 py-6 text-sm">Pas assez de données.</p>
            @else
                <div class="space-y-2.5">
                    @foreach ($topTrades as $i => $t)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200/70 bg-slate-50/30 p-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-100 text-brand-700 font-bold text-xs shrink-0">
                                    {{ $i + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate">{{ $t['trade_name'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $t['missions'] }} mission(s)</p>
                                </div>
                            </div>
                            <p class="text-sm font-bold text-brand-700 shrink-0">{{ number_format($t['total_eur'], 0, ',', ' ') }} €</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-soft p-6">
            <h2 class="inline-flex items-center gap-2 text-sm font-bold text-slate-900 mb-4">
                <x-ui.icon name="receipt" class="w-4 h-4 text-brand-600" />
                Décomposition revenus
            </h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-slate-600">Missions</span>
                    <span class="font-semibold text-slate-900">{{ number_format($current['mission_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-slate-600 inline-flex items-center gap-1.5">
                        <x-ui.icon name="gift" class="w-3.5 h-3.5 text-amber-500" />
                        Pourboires
                    </span>
                    <span class="font-semibold text-amber-600">+{{ number_format($current['tips_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>
                <div class="border-t border-slate-200 pt-2.5 mt-2 flex justify-between items-center">
                    <span class="font-bold text-slate-900">Total brut</span>
                    <span class="text-base font-bold text-brand-700">{{ number_format($current['gross_cents'] / 100, 2, ',', ' ') }} €</span>
                </div>

                <div class="mt-4 space-y-1 rounded-lg bg-slate-50/50 border border-slate-200/70 p-3">
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Wallet crédité</span>
                        <span class="font-medium">{{ number_format($current['wallet_credited_cents'] / 100, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Wallet payé (Stripe)</span>
                        <span class="font-medium">{{ number_format($current['wallet_paid_out_cents'] / 100, 2, ',', ' ') }} €</span>
                    </div>
                </div>
            </div>

            <a href="{{ route('employe.wallet') ?? '#' }}" class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition">
                <x-ui.icon name="wallet" class="w-3.5 h-3.5" />
                Voir mon wallet détaillé
                <x-ui.icon name="arrow-right" class="w-3 h-3" />
            </a>
        </div>
    </div>
</div>
