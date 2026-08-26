{{--
    ApexCharts N'EST PAS DANS LE PAQUET GLOBAL : c'est une entree Vite dediee, chargee
    seulement par les pages qui dessinent. Sans cette pile, `dessinerActivite` n'existe pas
    et les deux graphiques restent VIDES — sans erreur, sans rien qui le dise.

    ET LA PILE EST CONDITIONNELLE, comme les graphiques qu'elle sert. Le module pese 565 Ko :
    un client qui n'a pas encore un seul point le telechargerait pour deux cadres qui ne
    s'affichent pas. C'est precisement le cas le plus frequent au lancement.

    `@once` : ce composant peut etre rendu deux fois sur une meme page sans dupliquer la
    balise.
--}}
@if($totalGagne > 0)
    @once
        @push('scripts')
            @vite(['resources/js/apexcharts.js'])
        @endpush
    @endonce
@endif

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
             @php($teinteDuNiveau = $currentTier?->color ?: 'var(--cx-violet)')
             style="background: linear-gradient(135deg, {{ $teinteDuNiveau }} 0%, color-mix(in srgb, {{ $teinteDuNiveau }} 30%, var(--brio-ink)) 100%);">
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
                            <p class="text-xl font-black" style="color: {{ $tier->color ?: 'var(--brio-ink)' }};">
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

        {{--
            CE QUE LE SOLDE NE DIT PAS.

            L'écran montrait un total, un palier et une liste paginée. Aucun des trois ne
            répond aux deux questions qui décident si le client continue : « est-ce que je
            gagne plus qu'avant ? » et « qu'est-ce qui me rapporte ? ». La série existait
            pourtant — quinze lignes à la fois, à compter à la main.

            Les données passent par des attributs `data-*`. Une expression imbriquée dans une
            directive Blade casse la compilation de la vue ENTIÈRE, et l'erreur se signale
            ailleurs.
        --}}
        @if($totalGagne > 0)
            <div class="grid gap-4 lg:grid-cols-2">
                <section class="brio-graphique" aria-labelledby="titre-points-mois">
                    <div class="brio-graphique-tete">
                        <h2 id="titre-points-mois" class="brio-graphique-titre">{{ __('Points gagnés par mois') }}</h2>
                        <p class="brio-graphique-note">{{ __('Les douze mois qui décident de votre niveau') }}</p>
                    </div>

                    <div class="brio-graphique-corps" wire:ignore x-data x-init="dessinerActivite($el)">
                        <div data-graphique
                             data-nom="{{ __('Points') }}"
                             data-totaux="{{ json_encode(array_column($pointsParMois, 'points')) }}"
                             data-libelles="{{ json_encode(array_column($pointsParMois, 'libelle')) }}"></div>
                    </div>
                </section>

                <section class="brio-graphique" aria-labelledby="titre-origine">
                    <div class="brio-graphique-tete">
                        <h2 id="titre-origine" class="brio-graphique-titre">{{ __('D’où viennent vos points') }}</h2>
                        <p class="brio-graphique-note">{{ number_format($totalGagne, 0, ',', ' ') }} {{ __('points gagnés au total') }}</p>
                    </div>

                    <div class="brio-graphique-corps" wire:ignore x-data x-init="dessinerRepartition($el)">
                        <div data-graphique
                             data-valeurs="{{ json_encode(array_column($origineDesPoints, 'points')) }}"
                             data-libelles="{{ json_encode(array_column($origineDesPoints, 'libelle')) }}"></div>
                    </div>

                    {{--
                        LE TABLEAU RESTE, SOUS L'ANNEAU. Un graphique ne se lit pas au lecteur
                        d'écran, et un chiffre exact ne se relève pas sur un angle.
                    --}}
                    <div class="brio-table-cadre mt-4">
                        <table class="w-full">
                            <caption class="sr-only">{{ __('Points gagnés par origine') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Origine') }}</th>
                                    <th scope="col" class="text-right">{{ __('Points') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($origineDesPoints as $origine)
                                    <tr>
                                        <td>{{ $origine['libelle'] }}</td>
                                        <td class="text-right tabular-nums">{{ number_format($origine['points'], 0, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif

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
                                {{-- `earn_booking`, `redeem` : des identifiants de base, montrés
                                     tels quels à qui vient voir ses points. --}}
                                <td class="px-4 py-2">{{ \App\Livewire\Client\LoyaltyDashboard::libelleDuType($tx->type) }}</td>
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
