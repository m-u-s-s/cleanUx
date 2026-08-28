<div class="space-y-6" data-phase2u-root="true">
    @include('livewire.admin.governance.security-checks')

    @includeIf('livewire.admin.readiness.layout-stack')

<div class="space-y-6" data-phase2s-root="true">
    @includeIf('livewire.admin.pilotage.layout-stack')

<x-page-shell
    title="📊 Business Dashboard"
    subtitle="Vue globale de la croissance, du chiffre d’affaires, des clients et des opérations.">

    {{-- La coquille de page n'espace pas ses enfants : le groupe porte l'ecart. --}}
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-business-kpi-card
                title="CA ce mois"
                value="{{ locale_currency($metrics['revenue_current_month']) }}"
                subtitle="Chiffre d’affaires estimé" />

            <x-business-kpi-card
                title="Croissance"
                value="{{ $metrics['revenue_growth'] }}%"
                subtitle="vs mois précédent" />

            <x-business-kpi-card
                title="Réservations"
                value="{{ $metrics['bookings_current_month'] }}"
                subtitle="Ce mois-ci" />

            <x-business-kpi-card
                title="Missions terminées"
                value="{{ $metrics['missions_completed_current_month'] }}"
                subtitle="Ce mois-ci" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <x-business-kpi-card
                title="Clients"
                value="{{ $metrics['clients_total'] }}"
                subtitle="Total clients" />

            <x-business-kpi-card
                title="Clients Premium"
                value="{{ $metrics['premium_clients'] }}"
                subtitle="Abonnements actifs" />

            <x-business-kpi-card
                title="Employés actifs"
                value="{{ $metrics['employees_total'] }}"
                subtitle="Terrain disponible" />

            <x-business-kpi-card
                title="Litiges ouverts"
                value="{{ $metrics['open_claims'] }}"
                subtitle="À traiter rapidement" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2">
                {{--
                    L'EVOLUTION DU CHIFFRE, EN GRAPHIQUE.

                    La serie etait calculee depuis toujours puis rendue en largeurs de `div` : six
                    traits empiles, un maximum RECALCULE a chaque tour de boucle, et aucune facon de
                    voir une tendance autrement qu'en comparant des longueurs a l'oeil.

                    Les donnees passent par des attributs `data-*`. Une expression imbriquee dans une
                    directive Blade casse la compilation de la vue ENTIERE, et l'erreur se signale
                    ailleurs.
                --}}
                <section class="brio-graphique" aria-labelledby="titre-evolution">
                    <div class="brio-graphique-tete">
                        <h2 id="titre-evolution" class="brio-graphique-titre">{{ __('Évolution du chiffre d\'affaires') }}</h2>
                        <p class="brio-graphique-note">{{ __('6 dernières semaines') }}</p>
                    </div>

                    <div class="brio-graphique-corps" wire:ignore x-data x-init="dessinerActivite($el)">
                        <div data-graphique
                             data-devise="{{ \App\View\Components\Money::deviseDuContexte() }}"
                             data-nom="{{ __('Chiffre d\'affaires') }}"
                             data-totaux="{{ json_encode(array_column($metrics['weekly_revenue'], 'revenue')) }}"
                             data-libelles="{{ json_encode(array_column($metrics['weekly_revenue'], 'label')) }}"></div>
                    </div>

                    {{--
                        LE TABLEAU RESTE, SOUS LE GRAPHIQUE.

                        Un graphique ne se lit pas au lecteur d'ecran, et le nombre de rendez-vous
                        n'apparait pas sur la courbe du chiffre. Les deux repondent a des questions
                        differentes.
                    --}}
                    <div class="brio-table-cadre mt-4">
                        <table class="w-full">
                            <caption class="sr-only">{{ __('Chiffre d\'affaires et réservations, par semaine') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Semaine') }}</th>
                                    <th scope="col" class="text-right">{{ __('Chiffre d\'affaires') }}</th>
                                    <th scope="col" class="text-right">{{ __('Réservations') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($metrics['weekly_revenue'] as $week)
                                    <tr>
                                        <td>{{ $week['label'] }}</td>
                                        <td class="text-right tabular-nums"><x-money :amount="(float) ($week['revenue'])" /></td>
                                        <td class="text-right tabular-nums">{{ $week['bookings'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
                <h3 class="font-semibold text-slate-900">💡 Insights rapides</h3>

                <div class="rounded-xl bg-blue-50 border border-blue-100 p-4 text-sm text-blue-800">
                    Panier moyen :
                    <span class="font-bold">
                        <x-money :amount="(float) ($metrics['avg_booking_value'])" />
                    </span>
                </div>

                <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4 text-sm text-emerald-800">
                    Taux premium :
                    <span class="font-bold">
                        {{ $metrics['clients_total'] > 0
                            ? round(($metrics['premium_clients'] / $metrics['clients_total']) * 100, 1)
                            : 0 }}%
                    </span>
                </div>

                <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 text-sm text-amber-800">
                    Litiges ouverts :
                    <span class="font-bold">
                        {{ $metrics['open_claims'] }}
                    </span>
                </div>

                <div class="rounded-xl bg-slate-50 border p-4 text-sm text-slate-700">
                    Conseil : surveille surtout le CA, le panier moyen, les litiges et le nombre de clients premium.
                </div>
            </div>
        </div>
    </div>
</x-page-shell>
</div>
</div>