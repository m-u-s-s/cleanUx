{{-- ============================================================
     Un côté de l'accueil : trois blocs de cartes, puis quatre questions.

     LE MÊME GABARIT SERT LES DEUX CÔTÉS. Le texte vient de
     lang/*/vitrine.php sous « accueil.client » ou « accueil.prestataire » ;
     écrire deux gabarits ferait diverger la mise en page au premier
     changement, et le côté prestataire finirait moins soigné que l'autre.

     $cote : 'client' ou 'prestataire'.
     ============================================================ --}}
@props(['cote'])

@php
    $chiffres = [
        'metiers' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersOuverts(),
        'immediat' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersEnImmediat(),
        'zones' => \App\Support\Vitrine\ChiffresDeLaVitrine::zonesOuvertes(),
    ];

    $t = fn (string $cle) => __('vitrine.accueil.'.$cote.'.'.$cle, $chiffres);
    $l = fn (string $cle) => (array) __('vitrine.accueil.'.$cote.'.'.$cle);

    $versCommande = Route::has('order.journey') ? route('order.journey') : route('booking.create');
    $destination = $cote === 'client' ? $versCommande : route('register');

    // Une image par bloc : elle ouvre le bloc, elle ne le décore pas.
    $illustrations = [
        'client' => ['client-prix', 'client-societe', 'client-poignee'],
        'prestataire' => ['presta-independant', 'presta-equipe', 'presta-preuve'],
    ][$cote];

    // Quatre cartes portent leur propre image : celles dont l'argument se montre
    // mieux qu'il ne se raconte. Les autres restent au texte.
    $vignettes = [
        'Votre voiture' => 'gagne-voiture',
        'Votre logement' => 'gagne-logement',
        'Le visage vérifié, au hasard' => 'confiance-facial',
        'Vos équipes sur le terrain' => 'gagne-societe',
    ];
@endphp

{{-- L'accroche du côté, sur son bandeau : le changement de côté doit SE VOIR
     avant d'être lu. --}}
<section class="cx-cote__accroche">
    <x-accueil.illustration :nom="$cote === 'client' ? 'client-large' : 'presta-large'"
                            alt="" hative class="cx-cote__bandeau" />
    <div class="cx-cote__accroche-corps">
        <div class="mx-auto w-full max-w-xl px-6 sm:px-10">
            <p class="cx-cote__accroche-texte">{{ $t('accroche') }}</p>
        </div>
    </div>
</section>

@foreach ($l('blocs') as $i => $bloc)
    <section class="cx-arg @if ($i % 2) cx-arg--pose @endif py-20 sm:py-24"
             @if ($bloc['cle'] === 'client-confiance' || $bloc['cle'] === 'presta-missions') id="confiance-{{ $cote }}" @endif
             aria-labelledby="cx-bloc-{{ $cote }}-{{ $i }}">
        <div class="mx-auto max-w-7xl px-6">

            {{-- L'en-tête du bloc : l'image et le titre côte à côte — un bandeau pleine
                 largeur écraserait le titre. L'image CHANGE DE CÔTÉ un bloc sur deux :
                 trois en-têtes identiques d'affilée donnent une page qui piétine. --}}
            <div class="cx-bloc__entete @if ($i % 2) cx-bloc__entete--inverse @endif">
                <x-accueil.illustration :nom="$illustrations[$i] ?? $illustrations[0]" :alt="$bloc['titre']" />
                <div class="cx-bloc__intro">
                    @if (! empty($bloc['surtitre']))
                        <p class="cx-subhead">{{ $bloc['surtitre'] }}</p>
                    @endif
                    <h4 id="cx-bloc-{{ $cote }}-{{ $i }}" class="cx-arg__titre cx-headline cx-balance">
                        {{ $bloc['titre'] }}
                    </h4>
                    @if (! empty($bloc['sous_titre']))
                        <p class="cx-arg__chapeau cx-body-readable">{{ __($bloc['sous_titre'], $chiffres) }}</p>
                    @endif
                </div>
            </div>

            <div class="cx-arg__grille cx-arg__grille--trois">
                @foreach ($bloc['cartes'] as $carte)
                    @php($vignette = $vignettes[$carte['titre']] ?? null)
                    <article class="cx-arg__carte @if ($vignette) cx-arg__carte--illustree @endif">
                        @if ($vignette)
                            <x-accueil.illustration :nom="$vignette" :alt="$carte['titre']" />
                        @endif
                        <div class="cx-arg__carte-corps">
                            <h5 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h5>
                            <p class="cx-arg__texte">{{ __($carte['texte'], $chiffres) }}</p>
                            <p class="cx-arg__pastille @if ($cote === 'prestataire') cx-arg__pastille--gain @endif">
                                {{ __($carte['pastille'], $chiffres) }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>

            @if (! empty($bloc['bouton']))
                <div class="mt-12 text-center">
                    <span class="cx-magnetic" data-cx-magnetic="0.3">
                        <x-ui.button :href="$destination" variant="amber" size="xl" icon="arrow-right" iconPosition="right">
                            {{ $bloc['bouton'] }}
                        </x-ui.button>
                    </span>
                </div>
            @endif
        </div>
    </section>
@endforeach

{{-- Les quatre questions du côté. --}}
<section class="cx-arg cx-arg--pose py-20 sm:py-24" aria-labelledby="cx-questions-{{ $cote }}">
    <div class="mx-auto max-w-3xl px-6">
        <h4 id="cx-questions-{{ $cote }}" class="cx-arg__titre cx-headline cx-balance text-center">
            {{ $t('questions_titre') }}
        </h4>

        <div class="mt-10 space-y-3">
            @foreach ($l('questions') as $item)
                <details class="cx-arg__question">
                    <summary>
                        <span>{{ $item['q'] }}</span>
                        <span class="cx-arg__croix" aria-hidden="true">+</span>
                    </summary>
                    <p class="cx-arg__texte">{{ __($item['r'], $chiffres) }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- L'appel final du côté. --}}
<section class="cx-cote__final">
    <div class="mx-auto max-w-4xl px-6 text-center">
        <h4 class="cx-cote__final-titre">{{ $t('final_titre') }}</h4>
        <p class="cx-cote__final-texte">{{ __($t('final_texte'), $chiffres) }}</p>
        <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
            <span class="cx-magnetic" data-cx-magnetic="0.3">
                <x-ui.button :href="$destination" variant="amber" size="xl" icon="arrow-right" iconPosition="right">
                    {{ $t('final_bouton') }}
                </x-ui.button>
            </span>
            {{-- La porte vers l'autre côté : un même compte fait les deux. --}}
            <button type="button" class="cx-cote__bascule-lien"
                    @click="choisir('{{ $cote === 'client' ? 'prestataire' : 'client' }}')">
                {{ $t('final_autre') }}
                <x-ui.icon name="arrow-right" class="h-4 w-4" />
            </button>
        </div>
    </div>
</section>
