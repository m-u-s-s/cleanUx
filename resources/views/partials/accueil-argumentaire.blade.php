{{-- ============================================================
     Brio — l'argumentaire de l'accueil.

     DEUX PORTES, UN SEUL ÉCRAN. Le visiteur choisit « je commande » ou « je gagne »,
     et ne lit que sa moitié : la page faisait le double de long quand elle montrait
     les deux à la suite.

     TOUT LE TEXTE VIT DANS lang/*/vitrine.php sous « accueil » : il passe par __()
     et se modifie depuis /admin/traductions sans toucher au code.

     AUCUN CHIFFRE N'EST ÉCRIT ICI. Ils sont lus dans le moteur au rendu
     (App\Support\Vitrine\ChiffresDeLaVitrine).
     ============================================================ --}}
@php
    $chiffres = [
        'metiers' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersOuverts(),
        'immediat' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersEnImmediat(),
        'sansQuestionnaire' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersSansQuestionnaire(),
        'zones' => \App\Support\Vitrine\ChiffresDeLaVitrine::zonesOuvertes(),
    ];

    $a = fn (string $cle) => __('vitrine.accueil.'.$cle, $chiffres);
    $liste = fn (string $cle) => (array) __('vitrine.accueil.'.$cle);

    $versCommande = Route::has('order.journey') ? route('order.journey') : route('booking.create');
    $versInscription = route('register');

    // Chaque carte « gagner » mène là où l'on gagne vraiment, quand la route existe.
    $destinations = [
        'Recevoir des missions' => $versInscription,
        'Mettre ma voiture en location' => Route::has('peer.catalogue') ? route('peer.catalogue') : $versInscription,
        'Mettre mon logement en location' => Route::has('peer.sejours') ? route('peer.sejours') : $versInscription,
        'Ouvrir un compte société' => $versInscription,
    ];
@endphp

{{-- ─────────────────────────────────────────── LES DEUX PORTES --}}
<section id="commencer" class="cx-arg cx-arg--pose scroll-mt-20 py-20 sm:py-24"
         x-data="{ cote: 'client' }"
         aria-labelledby="cx-arg-portes">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('portes.surtitre') }}</p>
            <h3 id="cx-arg-portes" class="cx-arg__titre cx-headline cx-balance">{{ $a('portes.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('portes.sous_titre') }}</p>
        </div>

        {{-- Les deux portes restent visibles : on change d'avis sans remonter la page. --}}
        <div class="cx-arg__portes" role="tablist" aria-label="{{ $a('portes.titre') }}">
            @foreach (['client', 'gagnant'] as $cle)
                @php($porte = $liste('portes.'.$cle))
                <button type="button"
                        class="cx-arg__porte"
                        role="tab"
                        :class="cote === '{{ $cle }}' && 'is-choisie'"
                        :aria-selected="cote === '{{ $cle }}' ? 'true' : 'false'"
                        aria-controls="cx-arg-volet-{{ $cle }}"
                        id="cx-arg-porte-{{ $cle }}"
                        @click="cote = '{{ $cle }}'">
                    <span class="cx-arg__porte-titre">{{ $porte['titre'] }}</span>
                    <span class="cx-arg__porte-phrase">{{ $porte['phrase'] }}</span>
                    <span class="cx-arg__porte-lien">
                        {{ $porte['lien'] }}
                        <x-ui.icon name="arrow-right" class="h-4 w-4" />
                    </span>
                </button>
            @endforeach
        </div>

        {{-- ── VOLET CLIENT ────────────────────────────────────────────── --}}
        <div id="cx-arg-volet-client" role="tabpanel" aria-labelledby="cx-arg-porte-client"
             x-show="cote === 'client'" class="cx-arg__volet">
            <div class="mx-auto max-w-2xl text-center">
                <p class="cx-subhead">{{ $a('client.surtitre') }}</p>
                <h4 class="cx-arg__titre cx-headline cx-balance">{{ $a('client.titre') }}</h4>
                <p class="cx-arg__chapeau cx-body-readable">{{ $a('client.sous_titre') }}</p>
            </div>

            <div class="cx-arg__grille">
                @foreach ($liste('client.cartes') as $carte)
                    <article class="cx-arg__carte @if ($carte['image']) cx-arg__carte--illustree @endif">
                        @if ($carte['image'])
                            <x-accueil.illustration :nom="$carte['image']" :alt="$carte['titre']" />
                        @endif
                        <div class="cx-arg__carte-corps">
                            <h5 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h5>
                            <p class="cx-arg__texte">{{ __($carte['texte'], $chiffres) }}</p>
                            <p class="cx-arg__pastille">{{ __($carte['chiffre'], $chiffres) }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <x-ui.button :href="$versCommande" variant="amber" size="xl" icon="arrow-right" iconPosition="right">
                    {{ $a('client.bouton') }}
                </x-ui.button>
            </div>
        </div>

        {{-- ── VOLET GAGNANT ───────────────────────────────────────────── --}}
        <div id="cx-arg-volet-gagnant" role="tabpanel" aria-labelledby="cx-arg-porte-gagnant"
             x-show="cote === 'gagnant'" x-cloak class="cx-arg__volet">
            <div class="mx-auto max-w-2xl text-center">
                <p class="cx-subhead">{{ $a('gagnant.surtitre') }}</p>
                <h4 class="cx-arg__titre cx-headline cx-balance">{{ $a('gagnant.titre') }}</h4>
                <p class="cx-arg__chapeau cx-body-readable">{{ $a('gagnant.sous_titre') }}</p>
            </div>

            <div class="cx-arg__grille cx-arg__grille--gagner">
                @foreach ($liste('gagnant.cartes') as $carte)
                    <article class="cx-arg__carte cx-arg__carte--illustree">
                        <x-accueil.illustration :nom="$carte['image']" :alt="$carte['titre']" />
                        <div class="cx-arg__carte-corps">
                            <h5 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h5>
                            <p class="cx-arg__texte">{{ __($carte['texte'], $chiffres) }}</p>
                            <p class="cx-arg__pastille cx-arg__pastille--gain">{{ $carte['chiffre'] }}</p>
                            <a href="{{ $destinations[$carte['lien']] ?? $versInscription }}" class="cx-arg__lien">
                                {{ $carte['lien'] }}
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ──────────────────────────────────────── CE QUI PROTÈGE LES DEUX CÔTÉS --}}
<section id="confiance" class="cx-arg scroll-mt-20 py-20 sm:py-24" aria-labelledby="cx-arg-confiance">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('confiance.surtitre') }}</p>
            <h3 id="cx-arg-confiance" class="cx-arg__titre cx-headline cx-balance">{{ $a('confiance.titre') }}</h3>
        </div>

        <div class="cx-arg__grille cx-arg__grille--confiance">
            @foreach ($liste('confiance.points') as $point)
                <article class="cx-arg__carte @if ($point['image']) cx-arg__carte--illustree @endif">
                    @if ($point['image'])
                        <x-accueil.illustration :nom="$point['image']" :alt="$point['titre']" />
                    @endif
                    <div class="cx-arg__carte-corps">
                        <h5 class="cx-arg__carte-titre">{{ $point['titre'] }}</h5>
                        <p class="cx-arg__texte">{{ __($point['texte'], $chiffres) }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ───────────────────────────────────────────────────── LES ENTREPRISES --}}
<section id="b2b" class="cx-arg cx-arg--pose scroll-mt-20 py-20 sm:py-24" aria-labelledby="cx-arg-b2b">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('entreprises.surtitre') }}</p>
            <h3 id="cx-arg-b2b" class="cx-arg__titre cx-headline cx-balance">{{ $a('entreprises.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('entreprises.sous_titre') }}</p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($liste('entreprises.cartes') as $carte)
                <article class="cx-arg__carte cx-arg__carte--compacte">
                    <h5 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h5>
                    <p class="cx-arg__texte">{{ $carte['texte'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <x-ui.button :href="$versInscription" variant="outline" size="lg" icon="arrow-right" iconPosition="right">
                {{ $a('entreprises.bouton') }}
            </x-ui.button>
        </div>
    </div>
</section>

{{-- ───────────────────────────────────────────── QUATRE QUESTIONS --}}
<section class="cx-arg py-20 sm:py-24" aria-labelledby="cx-arg-questions">
    <div class="mx-auto max-w-3xl px-6">
        <h3 id="cx-arg-questions" class="cx-arg__titre cx-headline cx-balance text-center">{{ $a('questions.titre') }}</h3>

        <div class="mt-10 space-y-3">
            @foreach ($liste('questions.items') as $item)
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
