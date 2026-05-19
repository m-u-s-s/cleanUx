<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Programme fidélité</p>
            <h1 class="text-3xl font-black text-slate-900">Mon niveau {{ $currentTier?->name ?? '—' }}</h1>
            <p class="text-sm text-slate-500 mt-2">
                Gagnez des points à chaque mission, parrainage ou avis et débloquez des avantages exclusifs.
            </p>
        </div>

        {{-- Tier card --}}
        <div class="rounded-3xl p-8 shadow-xl text-white"
             style="background: linear-gradient(135deg, {{ $currentTier?->color ?? '#6366F1' }} 0%, #1e1b4b 100%);">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm uppercase font-bold opacity-80">Votre niveau actuel</p>
                    <p class="text-5xl font-black mt-2">
                        {{ $currentTier?->icon }} {{ $currentTier?->name ?? '—' }}
                    </p>
                    <p class="text-sm opacity-80 mt-3">
                        Points sur les 12 derniers mois : <span class="font-bold">{{ number_format($account->period_points, 0, ',', ' ') }}</span>
                        @if($currentTier && $currentTier->discount_percent > 0)
                            · Remise permanente <span class="font-bold">-{{ rtrim(rtrim(number_format((float) $currentTier->discount_percent, 1, ',', ' '), '0'), ',') }}%</span>
                        @endif
                    </p>
                </div>
            </div>

            @if($nextTier)
                <div class="mt-6">
                    <div class="flex items-center justify-between text-sm">
                        <span>{{ $currentTier?->name ?? 'Démarrage' }}</span>
                        <span class="font-bold">→ {{ $nextTier->icon }} {{ $nextTier->name }}</span>
                    </div>
                    <div class="mt-2 bg-white/20 rounded-full h-3 overflow-hidden">
                        <div class="bg-white h-3 transition-all" style="width: {{ $progressPercent }}%"></div>
                    </div>
                    <p class="text-xs opacity-80 mt-2">
                        Plus que <span class="font-bold">{{ number_format($pointsToNextTier, 0, ',', ' ') }}</span> points
                        pour atteindre le niveau {{ $nextTier->name }}.
                    </p>
                </div>
            @else
                <div class="mt-6 rounded-xl bg-white/10 p-4 text-sm">
                    🎉 Vous avez atteint le niveau maximum !
                </div>
            @endif
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Points cumulés</p>
                <p class="text-2xl font-black text-slate-900">{{ number_format($account->lifetime_points, 0, ',', ' ') }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Période courante</p>
                <p class="text-2xl font-black text-indigo-600">{{ number_format($account->period_points, 0, ',', ' ') }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Multiplicateur</p>
                <p class="text-2xl font-black text-emerald-600">x{{ number_format((float) $account->tierMultiplier(), 1, ',', ' ') }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs uppercase font-bold text-slate-500">Depuis</p>
                <p class="text-sm font-bold text-slate-700">
                    {{ optional($account->tier_started_at)->format('d/m/Y') ?? '—' }}
                </p>
            </div>
        </div>

        {{-- Tiers comparison --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 mb-4">Tous les niveaux</h2>
            <div class="space-y-3">
                @foreach($allTiers as $tier)
                    <div @class([
                        'flex items-start justify-between rounded-xl border p-4',
                        'bg-indigo-50 border-indigo-300' => $currentTier && $currentTier->id === $tier->id,
                    ])>
                        <div class="flex-1">
                            <p class="text-xl font-black" style="color: {{ $tier->color }};">
                                {{ $tier->icon }} {{ $tier->name }}
                            </p>
                            <p class="text-xs text-slate-500 mt-1">
                                Dès {{ number_format($tier->min_period_points, 0, ',', ' ') }} points / 12 mois
                            </p>
                            @if($tier->benefits)
                                <ul class="mt-2 text-sm text-slate-700 space-y-1">
                                    @foreach($tier->benefits as $benefit)
                                        <li>• {{ $benefit }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                        @if($currentTier && $currentTier->id === $tier->id)
                            <span class="rounded-full bg-indigo-600 px-3 py-1 text-xs font-bold text-white">Actuel</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Historique --}}
        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="p-4 border-b">
                <h2 class="text-lg font-bold text-slate-900">Historique des points</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Type</th>
                            <th class="px-4 py-2 text-left">Raison</th>
                            <th class="px-4 py-2 text-right">Points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($transactions as $tx)
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ $tx->occurred_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2"><span class="font-mono text-xs">{{ $tx->type }}</span></td>
                                <td class="px-4 py-2">{{ $tx->reason ?? '—' }}</td>
                                <td class="px-4 py-2 text-right font-bold {{ $tx->direction === 'credit' ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ $tx->direction === 'credit' ? '+' : '-' }}{{ number_format($tx->points, 0, ',', ' ') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">
                                Aucun point gagné pour le moment. Réservez votre première mission !
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $transactions->links() }}</div>
        </div>
    </div>
</div>
