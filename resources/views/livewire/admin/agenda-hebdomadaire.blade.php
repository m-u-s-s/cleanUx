{{--
    LA SEMAINE EN SEPT COLONNES.

    Elle etait rendue en grille `2xl:grid-cols-7` avec la carte LARGE du focus : 153 px de
    colonne mesures a 1568 px, pour une carte dessinee a 400. Les badges se chevauchaient
    l'heure, le nom du service disparaissait, et les deux cases « Employe / Charge » tombaient
    a une lettre par ligne.

    Deux densites, une seule mise en page : bande a defilement horizontal sous `xl` — la
    semaine garde son ordre et chaque jour respire — puis les sept colonnes en grille au-dela.
--}}
<div class="space-y-3">
    <div
        role="group"
        tabindex="0"
        aria-label="Agenda de la semaine, sept jours"
        {{-- La bande deborde jusqu'aux bords de la section : la carte coupee a droite est
             ce qui dit qu'il y a une suite. Les marges negatives valent exactement le
             rembourrage de la section, l'alignement du contenu ne bouge donc pas. --}}
        class="-mx-5 flex snap-x snap-mandatory gap-3 overflow-x-auto px-5 pb-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/60 md:-mx-6 md:px-6 xl:mx-0 xl:grid xl:grid-cols-7 xl:overflow-visible xl:px-0 xl:pb-0">
        @foreach($jours as $jour)
            @php
                $nombre = $jour['rdvs']->count();
                // Huit heures font la journee pleine : c'est le seuil que la charge equipe
                // emploie deja pour son propre gabarit.
                $remplissage = min(100, (int) round(($jour['total_minutes'] / 480) * 100));
                $charge = $jour['total_minutes'] >= 420;
            @endphp

            <section
                aria-label="{{ $jour['label'] }}"
                class="flex min-h-[11rem] w-[14.5rem] shrink-0 snap-start flex-col rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm transition duration-200 xl:w-auto xl:min-w-0 xl:shrink {{ $jour['is_focus'] ? 'ring-2 ring-sky-400/70' : '' }}">
                <header>
                    {{-- UNE PASTILLE, PLUS UNE ETIQUETTE. « Auj. » et « Focus » prenaient
                         44 px sur les 150 de la colonne : le nom du jour se coupait en
                         « Dim. 30/… » et « 0,0 h » passait a la ligne. Le repere reste,
                         le libelle part au lecteur d'ecran et a l'infobulle. --}}
                    <p class="flex items-center gap-1.5 text-sm font-black capitalize text-slate-900">
                        <span class="truncate">{{ $jour['short_label'] }}</span>

                        @if($jour['is_today'])
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" title="Aujourd’hui"></span>
                            <span class="sr-only">Aujourd’hui</span>
                        @elseif($jour['is_focus'])
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-sky-500" title="Jour ciblé"></span>
                            <span class="sr-only">Jour ciblé</span>
                        @endif
                    </p>

                    <p class="mt-0.5 truncate text-[11px] tabular-nums text-slate-500">
                        @if($nombre === 0)
                            Aucune mission
                        @else
                            {{ $nombre }} mission{{ $nombre > 1 ? 's' : '' }}
                            · {{ number_format($jour['total_hours'], 1, ',', ' ') }} h
                        @endif
                    </p>

                    {{-- LA CHARGE DU JOUR, ENFIN VISIBLE. Le sous-titre de la section la
                         promet depuis le debut ; seul un nombre la disait. `aria-hidden` :
                         la ligne au-dessus porte deja la meme information en toutes lettres. --}}
                    <div aria-hidden="true" class="mt-2 h-1 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                        <div
                            class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none {{ $charge ? 'bg-amber-500' : 'bg-sky-500' }}"
                            style="width: {{ $remplissage }}%"></div>
                    </div>

                    @if($jour['urgent_count'] > 0 || $jour['unassigned_count'] > 0)
                        <div class="mt-2 flex flex-wrap gap-1 text-[10px] font-bold">
                            @if($jour['urgent_count'] > 0)
                                <span class="rounded-full bg-rose-100 px-1.5 py-px text-rose-700">
                                    {{ $jour['urgent_count'] }} urgente{{ $jour['urgent_count'] > 1 ? 's' : '' }}
                                </span>
                            @endif

                            @if($jour['unassigned_count'] > 0)
                                <span class="rounded-full bg-amber-100 px-1.5 py-px text-amber-700">
                                    {{ $jour['unassigned_count'] }} sans employé
                                </span>
                            @endif
                        </div>
                    @endif
                </header>

                <div class="mt-3 flex flex-1 flex-col gap-2">
                    @forelse($jour['rdvs'] as $rdv)
                        <x-rdv-agenda-card :rdv="$rdv" />
                    @empty
                        {{-- L'etat vide occupait toute la hauteur de la colonne la plus chargee :
                             un pave blanc de 40 rem pour dire qu'il n'y a rien. --}}
                        <p class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-slate-200 px-2 py-5 text-center text-[11px] text-slate-500">
                            Rien de planifié
                        </p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{--
        LA MODALE SORT DE LA SECTION, ET C'EST OBLIGATOIRE.

        La section qui porte cet agenda est une surface de verre : `backdrop-filter` fait
        d'elle le BLOC CONTENEUR de tout `position: fixed` descendant. Mesure du 2026-08-30 :
        le fond de modale, pose en `fixed inset-0`, faisait 507 px de haut au lieu des 917 de
        la fenetre — panneau decentre, debordant par le bas, page a peine assombrie.

        `@teleport` la remonte dans `<body>` sans la sortir du composant : les `wire:click`
        continuent de viser `AgendaHebdomadaire`. Le bloc reste DANS la racine unique de la
        vue, sans quoi Livewire ne le rendrait pas du tout.
    --}}
    {{-- LA CONDITION EST DEHORS, et ce n'est pas cosmetique : `@teleport` rend un
         `<template x-teleport>` qu'Alpine clone A SON INITIALISATION. Emis vide puis rempli
         par un rafraichissement, le clone reste vide et rien ne s'affiche — mesure du
         2026-08-30. Emis seulement quand il y a quelque chose a montrer, il se clone plein. --}}
    @if($rdvOuvert)
        @teleport('body')
            @include('livewire.admin.agenda.modale-rdv')
        @endteleport
    @endif
</div>
