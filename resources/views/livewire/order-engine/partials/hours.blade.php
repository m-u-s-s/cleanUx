{{--
    COMBIEN DE TEMPS ? — le sélecteur d'heures des prestations vendues au temps.

    IL ÉTAIT ABSENT. Les méthodes existaient sur le composant — `choisirLesHeures`,
    `ajouterUneDemiHeure`, `heuresParDefaut` — et aucune vue ne les appelait : le client ne pouvait
    pas choisir ses heures, et une prestation horaire partait sur la durée par défaut sans que
    personne ne l'ait décidée. Ni `tsc` ni la suite ne disent quoi que ce soit d'un bouton qui
    n'existe pas.

    IL VIENT AVANT LES QUESTIONS, et c'est délibéré : sur une prestation horaire, la durée EST le
    prix. La placer après les questions ferait découvrir le montant au bout du parcours, alors que
    c'est la première chose que le client veut arbitrer.

    LA RÈGLE DU DÉPASSEMENT EST ANNONCÉE ICI, au moment exact du choix — pas dans les conditions
    générales, pas dans un courriel de confirmation. Une majoration qu'on découvre sur sa facture
    est un litige ; annoncée là où l'on décide de la durée, c'est une information qui sert.
--}}
@if ($this->estFactureALHeure())
    @php
        $tarif = $this->tarifHoraireCents();
        $heures = $heuresChoisies ?? $this->heuresParDefaut();
        $pas = $this->pasDuSelecteur();
        $min = (float) config('order_engine.hourly_min_hours', 1.0);
        $max = (float) config('order_engine.hourly_max_hours', 12.0);
    @endphp

    <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="heures-titre">
        <h2 id="heures-titre" class="text-lg font-semibold text-slate-900">Combien de temps ?</h2>

        @if ($tarif !== null)
            <p class="mt-1 text-sm text-slate-500">
                {{ number_format($tarif / 100, 2, ',', ' ') }} € de l’heure.
            </p>
        @endif

        <div class="mt-4 flex items-center gap-4">
            {{--
                DEUX BOUTONS ET UN CHIFFRE, plutôt qu'un champ libre : sur un téléphone, saisir
                « 2,5 » demande d'ouvrir un clavier numérique, de trouver la virgule, et laisse
                entrer « 250 ». Les bornes sont portées par le composant, pas par ce fichier.
            --}}
            <button type="button" wire:click="retirerUneDemiHeure"
                @disabled($heures <= $min)
                aria-label="Retirer {{ $pas * 60 }} minutes"
                class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 text-xl font-light text-slate-700 transition hover:border-slate-300 disabled:opacity-30">
                &minus;
            </button>

            <div class="min-w-[112px] text-center">
                <span class="text-3xl font-light tabular-nums text-slate-900" data-testid="heures-choisies">
                    {{ rtrim(rtrim(number_format($heures, 1, ',', ' '), '0'), ',') }}
                </span>
                <span class="ml-1 text-sm text-slate-500">{{ $heures > 1 ? 'heures' : 'heure' }}</span>
            </div>

            <button type="button" wire:click="ajouterUneDemiHeure"
                @disabled($heures >= $max)
                aria-label="Ajouter {{ $pas * 60 }} minutes"
                class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 text-xl font-light text-slate-700 transition hover:border-slate-300 disabled:opacity-30">
                +
            </button>
        </div>

        {{--
            L'ANNONCE DE LA RÈGLE — la même phrase que les conditions générales, le contrat
            prestataire et l'écran de suivi. Une règle formulée quatre fois différemment se lit
            comme quatre règles.
        --}}
        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
            {{ \App\Support\Pricing\HourlyRuleText::courte() }}
        </p>
    </section>
@endif
