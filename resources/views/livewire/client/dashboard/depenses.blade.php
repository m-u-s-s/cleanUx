{{--
    LA COURBE DES DÉPENSES — le premier graphique de l'espace client.

    Il n'y en avait aucun : quatre compteurs, et rien qui montre une évolution. Un compteur
    dit « vous avez 12 interventions » ; une courbe dit « vos dépenses ont doublé en mars » —
    c'est la seconde qui sert à décider.

    LE BLOC DISPARAÎT S'IL N'Y A RIEN À MONTRER. Une courbe plate à zéro sur six mois n'informe
    pas : elle occupe l'écran d'un client qui vient de s'inscrire et lui apprend que la
    plateforme affiche du vide.
--}}
@php($depenses = $this->depensesParMois)
@php($totalDepenses = array_sum(array_column($depenses, 'montant')))

@if ($totalDepenses > 0)
    <section class="brio-graphique" aria-labelledby="titre-depenses">
        <div class="brio-graphique-tete">
            <h2 id="titre-depenses" class="brio-graphique-titre">{{ __('Vos dépenses') }}</h2>
            <p class="brio-graphique-note">{{ __('Six derniers mois') }}</p>
        </div>

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
                        chart: { type: 'area', height: 240 },
                        series: [{
                            name: @js(__('Dépenses')),
                            data: @js(array_map(static fn ($m) => $m['montant'], $depenses)),
                        }],
                        xaxis: { categories: @js(array_map(static fn ($m) => $m['libelle'], $depenses)) },
                        yaxis: {
                            labels: {
                                formatter: (v) => new Intl.NumberFormat(
                                    document.documentElement.lang || 'fr',
                                    { style: 'currency', currency: @js(\App\View\Components\Money::deviseDuContexte()), maximumFractionDigits: 0 },
                                ).format(v),
                            },
                        },
                    }).render();
                };

                dessiner();
                document.addEventListener('brio:theme', dessiner);
            "
        >
            <div data-graphique></div>
        </div>
    </section>
@endif
