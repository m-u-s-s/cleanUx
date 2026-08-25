@push('scripts')
    @vite(['resources/js/apexcharts.js'])
@endpush

<div class="py-8 max-w-6xl mx-auto px-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <p class="ui-page-eyebrow !mt-0">{{ __('Espace prestataire') }}</p>
            <h1 class="ui-page-title">{{ __('Mes revenus') }}</h1>
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
                        <x-money :amount="(float) ($current['gross_cents'] / 100)" />
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
                        <x-money :amount="(float) ($current['tips_cents'] / 100)" />
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
                        <x-money :amount="(float) ($current['wallet_paid_out_cents'] / 100)" />
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
            {{--
                UN VRAI GRAPHIQUE, PAS DES BARRES EN CSS.

                La serie etait deja calculee — le composant la nomme « Timeseries pour graph » —
                mais la vue la rendait en `<div>` dont la hauteur etait un nombre de pixels. Pas
                d'infobulle, pas de courbe, et une hauteur en dur qui ne suivait aucun ecran.

                ApexCharts est deja charge et THEME globalement : la vue n'a donc a declarer que
                ses donnees. Les couleurs, la police, la grille et l'infobulle de verre viennent
                de `resources/js/apexcharts.js`, et suivent la bascule de mode.
            --}}
            <div
                class="brio-graphique-corps"
                wire:ignore
                x-data
                x-init="
                    const dessiner = () => {
                        const cible = $el.querySelector('[data-graphique]');
                        if (! cible || typeof ApexCharts === 'undefined') return;

                        cible.innerHTML = '';

                        new ApexCharts(cible, {
                            chart: { type: 'area', height: 260, sparkline: { enabled: false } },
                            series: [{
                                name: @js(__('Revenus')),
                                data: @js(array_map(fn ($p) => round((float) $p['amount_eur'], 2), $series)),
                            }],
                            xaxis: { categories: @js(array_column($series, 'label')) },
                            yaxis: { labels: { formatter: (v) => new Intl.NumberFormat(document.documentElement.lang || 'fr').format(Math.round(v)) } },
                        }).render();
                    };

                    dessiner();
                    document.addEventListener('brio:theme', dessiner);
                "
            >
                <div data-graphique></div>
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
                            <p class="text-sm font-bold text-brand-700 shrink-0"><x-money :amount="(float) ($t['total_eur'])" :decimals="0" /></p>
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
                    <span class="font-semibold text-slate-900"><x-money :amount="(float) ($current['mission_cents'] / 100)" /></span>
                </div>
                <div class="flex justify-between items-center py-1.5">
                    <span class="text-slate-600 inline-flex items-center gap-1.5">
                        <x-ui.icon name="gift" class="w-3.5 h-3.5 text-amber-500" />
                        Pourboires
                    </span>
                    <span class="font-semibold text-amber-600">+<x-money :amount="(float) ($current['tips_cents'] / 100)" /></span>
                </div>
                <div class="border-t border-slate-200 pt-2.5 mt-2 flex justify-between items-center">
                    <span class="font-bold text-slate-900">Total brut</span>
                    <span class="text-base font-bold text-brand-700"><x-money :amount="(float) ($current['gross_cents'] / 100)" /></span>
                </div>

                <div class="mt-4 space-y-1 rounded-lg bg-slate-50/50 border border-slate-200/70 p-3">
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Wallet crédité</span>
                        <span class="font-medium"><x-money :amount="(float) ($current['wallet_credited_cents'] / 100)" /></span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600">
                        <span>Wallet payé (Stripe)</span>
                        <span class="font-medium"><x-money :amount="(float) ($current['wallet_paid_out_cents'] / 100)" /></span>
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
    {{-- ─── E15 : les statistiques d'offres ────────────────────────────────── --}}
    {{--
        TOUT EST DÉJÀ DANS `mission_assignments` et personne ne le lisait. C'est la réponse exacte à
        « pourquoi est-ce que je reçois moins de courses qu'avant » — une question à laquelle on ne
        pouvait répondre qu'au ressenti.
    --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" data-test="stats-offres">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Mes offres (30 derniers jours)</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div>
                <p class="text-xs text-slate-400">Reçues</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">{{ $offres['offers_count'] }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Acceptées</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">
                    {{ $offres['acceptance_rate'] !== null ? $offres['acceptance_rate'].' %' : '—' }}
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Temps de réponse</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">
                    {{ $offres['median_response_seconds'] !== null ? $offres['median_response_seconds'].' s' : '—' }}
                </p>
                {{-- La MÉDIANE, pas la moyenne : une offre répondue depuis un tunnel décalerait une
                     moyenne au point de la rendre absurde. --}}
                <p class="text-xs text-slate-400">médiane</p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Sans réponse</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">{{ $offres['expired_count'] }}</p>
                {{-- Une expiration se corrige en répondant plus vite, un refus en changeant ce
                     qu'on accepte : les mélanger donnerait un conseil faux. --}}
                <p class="text-xs text-slate-400">expirées</p>
            </div>
        </div>

        @if (count($offres['decline_reasons']) > 0)
        <div class="mt-4 border-t border-slate-100 pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Motifs de refus</p>
            <ul class="space-y-1 text-sm text-slate-600">
                @foreach ($offres['decline_reasons'] as $motif)
                <li>{{ $motif['reason'] }} — {{ $motif['count'] }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    {{-- ─── E14 : le virement instantané ───────────────────────────────────── --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" data-test="virement-express">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Virement instantané</h2>
        <p class="mt-1 text-sm text-slate-500">
            Le virement ordinaire reste gratuit. Celui-ci arrive tout de suite, contre des frais.
        </p>

        @if ($refusExpress)
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            {{ $refusExpress }}
        </p>
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label for="montant-express" class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Montant (€)</span>
                <input id="montant-express" type="text" wire:model.live="montantExpress" placeholder="50,00"
                    class="w-40 rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <button type="button" wire:click="demanderLeVirementExpress"
                @disabled(! $devisExpress['eligible'])
                class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-40">
                Recevoir maintenant
            </button>
        </div>

        {{--
            LES FRAIS S'AFFICHENT AVANT, EN EUROS. « 1,5 % » se lit et ne se comprend pas ;
            « 2,40 € » se comprend. Le NET est le seul chiffre qui compte pour celui qui reçoit.
        --}}
        @if ($devisExpress['amount_cents'] > 0)
        <p class="mt-3 text-sm {{ $devisExpress['eligible'] ? 'text-slate-700' : 'text-slate-500' }}">
            @if ($devisExpress['eligible'])
            Frais : <x-money :amount="(float) ($devisExpress['fee_cents'] / 100)" /> —
            vous recevrez <strong><x-money :amount="(float) ($devisExpress['net_cents'] / 100)" /></strong>.
            @else
            Minimum <x-money :amount="(float) ($devisExpress['minimum_cents'] / 100)" /> :
            en dessous, les frais représenteraient une part trop importante.
            @endif
        </p>
        @endif
    </div>

    {{-- ─── E18 : l'assistant fiscal ───────────────────────────────────────── --}}
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5" data-test="assistant-fiscal">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-500">Mes revenus déclarables</h2>

            <label for="annee-fiscale" class="flex items-center gap-2">
                <span class="text-xs text-slate-500">Année</span>
                <select id="annee-fiscale" wire:model.live="anneeFiscale"
                    class="rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    @for ($a = (int) now()->year; $a >= (int) now()->year - 4; $a--)
                    <option value="{{ $a }}">{{ $a }}</option>
                    @endfor
                </select>
            </label>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-xs text-slate-400">Encaissé</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($fiscal['gross_cents'] / 100)" />
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Net de reprises</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($fiscal['net_cents'] / 100)" />
                </p>
            </div>
            <div>
                <p class="text-xs text-slate-400">Charges estimées</p>
                <p class="text-xl font-bold tabular-nums text-slate-900">
                    <x-money :amount="(float) ($fiscal['estimated_charges_cents'] / 100)" />
                </p>
            </div>
        </div>

        {{--
            L'ESTIMATION EST UNE ESTIMATION, ET LE MOT COMPTE. Les taux dépendent du statut, du
            pays, du chiffre d'affaires : annoncer un montant sans le dire ferait provisionner faux,
            et le mauvais sens se découvre au moment de payer.
        --}}
        <p class="mt-3 text-xs text-slate-500">
            Estimation au taux de {{ $fiscal['charges_rate_percent'] }} %, à vérifier avec votre
            comptable. La plateforme ne connaît pas votre statut fiscal.
        </p>

        <button type="button" wire:click="exporterLesRevenus"
            class="mt-4 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Exporter {{ $anneeFiscale }} en CSV
        </button>
    </div>
</div>
