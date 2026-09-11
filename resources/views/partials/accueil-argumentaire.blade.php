{{-- ============================================================
     Brio — l'argumentaire de l'accueil.

     TOUT LE TEXTE VIT DANS lang/*/vitrine.php, sous la clé « accueil » : il passe
     par __() et se modifie depuis /admin/traductions sans toucher au code.

     AUCUN CHIFFRE N'EST ÉCRIT ICI. Ils sont lus dans le moteur au rendu
     (App\Support\Vitrine\ChiffresDeLaVitrine) : la page annonçait « 30+ métiers
     dans 9 pays » quand le catalogue en ouvrait seize dans un seul.
     ============================================================ --}}
@php
    $chiffres = [
        'metiers' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersOuverts(),
        'immediat' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersEnImmediat(),
        'sansQuestionnaire' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersSansQuestionnaire(),
        'zones' => \App\Support\Vitrine\ChiffresDeLaVitrine::zonesOuvertes(),
    ];

    $a = fn (string $cle, array $extra = []) => __('vitrine.accueil.'.$cle, $extra + $chiffres);
    $liste = fn (string $cle) => (array) __('vitrine.accueil.'.$cle);

    $versCommande = Route::has('order.journey')? route('order.journey'): route('booking.create');
    $versInscription = route('register');
@endphp

{{-- ─────────────────────────────────────────── LES QUATRE SITUATIONS --}}
<section id="situations" class="cx-arg scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-situations">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('situations.surtitre') }}</p>
            <h3 id="cx-arg-situations" class="cx-arg__titre cx-headline cx-balance">{{ $a('situations.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('situations.sous_titre') }}</p>
        </div>

        <div class="mt-14 grid gap-6 lg:grid-cols-2">
            @foreach ($liste('situations.cartes') as $i => $carte)
                <article class="cx-arg__carte" data-cx-reveal data-cx-delay="{{ $i * 80 }}">
                    <h4 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h4>

                    @foreach ($carte['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach

                    <ul class="cx-arg__puces">
                        @foreach ($carte['puces'] as $puce)
                            <li>{{ __($puce, $chiffres) }}</li>
                        @endforeach
                    </ul>

                    @if ($carte['bouton'])
                        <a href="{{ str_contains($carte['bouton'], 'entreprise') ? $versInscription : $versCommande }}"
                           class="cx-arg__lien">
                            {{ $carte['bouton'] }}
                            <x-ui.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="cx-arg__note cx-arg__note--large">{{ $a('situations.note') }}</p>
    </div>
</section>

{{-- ─────────────────────────────────────────────── LES TROIS TEMPS --}}
<section id="fonctionnement" class="cx-arg cx-arg--pose scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-fonctionnement">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('fonctionnement.surtitre') }}</p>
            <h3 id="cx-arg-fonctionnement" class="cx-arg__titre cx-headline cx-balance">{{ $a('fonctionnement.titre') }}</h3>
        </div>

        <ol class="mt-14 grid gap-6 md:grid-cols-3">
            @foreach ($liste('fonctionnement.temps') as $i => $temps)
                <li class="cx-arg__carte cx-arg__carte--temps" data-cx-reveal data-cx-delay="{{ $i * 90 }}">
                    <span class="cx-arg__rang" aria-hidden="true">{{ $i + 1 }}</span>
                    <h4 class="cx-arg__carte-titre">{{ $temps['titre'] }}</h4>
                    @foreach ($temps['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ──────────────────────────────────────── QUI ENTRE, ET CE QUI RESTE --}}
<section id="confiance" class="cx-arg scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-confiance">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('confiance.surtitre') }}</p>
            <h3 id="cx-arg-confiance" class="cx-arg__titre cx-headline cx-balance">{{ $a('confiance.titre') }}</h3>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2">
            @foreach ($liste('confiance.points') as $i => $point)
                <article class="cx-arg__carte" data-cx-reveal data-cx-delay="{{ $i * 80 }}">
                    <h4 class="cx-arg__carte-titre">{{ $point['titre'] }}</h4>
                    @foreach ($point['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ────────────────────────────────────────────────── LE PRIX, EN ENTIER --}}
<section id="prix" class="cx-arg cx-arg--pose scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-prix">
    <div class="mx-auto max-w-7xl px-6">
        <p class="cx-arg__position">{{ $a('position') }}</p>

        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('prix.surtitre') }}</p>
            <h3 id="cx-arg-prix" class="cx-arg__titre cx-headline cx-balance">{{ $a('prix.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('prix.sous_titre') }}</p>
        </div>

        <div class="mx-auto mt-12 max-w-3xl">
            @foreach ($liste('prix.paragraphes') as $paragraphe)
                <p class="cx-arg__texte cx-arg__texte--large">{{ __($paragraphe, $chiffres) }}</p>
            @endforeach
        </div>

        <div class="mt-14 grid gap-6 lg:grid-cols-2">
            <article class="cx-arg__carte" data-cx-reveal>
                <h4 class="cx-arg__carte-titre">{{ $a('prix.variations.titre') }}</h4>
                <ul class="cx-arg__puces">
                    @foreach ($liste('prix.variations.puces') as $puce)
                        <li>{{ __($puce, $chiffres) }}</li>
                    @endforeach
                </ul>
                <p class="cx-arg__note">{{ $a('prix.note_acompte') }}</p>
            </article>

            <article class="cx-arg__carte" data-cx-reveal data-cx-delay="90">
                <h4 class="cx-arg__carte-titre">{{ $a('prix.annulation.titre') }}</h4>
                <ul class="cx-arg__puces">
                    @foreach ($liste('prix.annulation.puces') as $puce)
                        <li>{{ __($puce, $chiffres) }}</li>
                    @endforeach
                </ul>
            </article>
        </div>

        {{-- L'AVEU. Une page qui ne concède rien ne se croit pas ; celle-ci dit son prix. --}}
        <div class="cx-arg__aveu" data-cx-reveal>
            <h4 class="cx-arg__aveu-titre">{{ $a('prix.aveu.titre') }}</h4>
            @foreach ($liste('prix.aveu.paragraphes') as $paragraphe)
                <p class="cx-arg__texte">{{ __($paragraphe, $chiffres) }}</p>
            @endforeach
        </div>
    </div>
</section>

{{-- ──────────────────────────────────────────────────── LES ENGAGEMENTS --}}
<section id="engagements" class="cx-arg scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-engagements">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('engagements.surtitre') }}</p>
            <h3 id="cx-arg-engagements" class="cx-arg__titre cx-headline cx-balance">{{ $a('engagements.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('engagements.sous_titre') }}</p>
        </div>

        <ul class="cx-arg__engagements">
            @foreach ($liste('engagements.puces') as $i => $puce)
                <li data-cx-reveal data-cx-delay="{{ $i * 70 }}">
                    <x-ui.icon name="check" class="cx-arg__coche" />
                    <span>{{ __($puce, $chiffres) }}</span>
                </li>
            @endforeach
        </ul>

        <p class="cx-arg__chiffre">{{ $a('engagements.chiffre') }}</p>
        <p class="cx-arg__note cx-arg__note--large">{{ $a('engagements.note') }}</p>

        <div class="mt-10 text-center">
            <x-ui.button :href="$versCommande" variant="amber" size="xl" icon="arrow-right" iconPosition="right">
                {{ $a('engagements.bouton') }}
            </x-ui.button>
        </div>
    </div>
</section>

{{-- ─────────────────────────── VOUS AVEZ DÉJÀ QUELQU'UN ? TANT MIEUX --}}
<section class="cx-arg cx-arg--pose py-24 sm:py-28" aria-labelledby="cx-arg-habitude">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('habitude.surtitre') }}</p>
            <h3 id="cx-arg-habitude" class="cx-arg__titre cx-headline cx-balance">{{ $a('habitude.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('habitude.sous_titre') }}</p>
        </div>

        <p class="cx-arg__texte cx-arg__texte--large mx-auto mt-8 max-w-2xl text-center">{{ $a('habitude.intro') }}</p>

        <div class="mt-14 grid gap-6 md:grid-cols-2">
            @foreach ($liste('habitude.moments') as $i => $moment)
                <article class="cx-arg__carte" data-cx-reveal data-cx-delay="{{ $i * 80 }}">
                    <h4 class="cx-arg__carte-titre">{{ $moment['titre'] }}</h4>
                    @foreach ($moment['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach
                    @foreach ($moment['notes'] as $note)
                        <p class="cx-arg__note">{{ __($note, $chiffres) }}</p>
                    @endforeach
                </article>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <x-ui.button :href="$versCommande" variant="outline" size="lg" icon="arrow-right" iconPosition="right">
                {{ $a('habitude.bouton') }}
            </x-ui.button>
        </div>
    </div>
</section>

{{-- ──────────────────────────────────────────── LE CÔTÉ PRESTATAIRE --}}
<section id="prestataires" class="cx-arg cx-arg--prestataire scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-prestataire">
    <div class="mx-auto max-w-7xl px-6">
        {{-- LA PHRASE CHARNIÈRE : elle répond à « quel est votre intérêt là-dedans ? »
             avant que le lecteur ne se le demande. --}}
        <p class="cx-arg__charniere">{{ $a('prestataire.charniere') }}</p>

        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('prestataire.surtitre') }}</p>
            <h3 id="cx-arg-prestataire" class="cx-arg__titre cx-headline cx-balance">{{ $a('prestataire.titre') }}</h3>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($liste('prestataire.cartes') as $i => $carte)
                <article class="cx-arg__carte" data-cx-reveal data-cx-delay="{{ $i * 70 }}">
                    <span class="cx-arg__rang" aria-hidden="true">{{ $i + 1 }}</span>
                    <h4 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h4>
                    @foreach ($carte['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach
                </article>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <x-ui.button :href="$versInscription" variant="amber" size="xl" icon="arrow-right" iconPosition="right">
                {{ $a('prestataire.bouton') }}
            </x-ui.button>
        </div>
    </div>
</section>

{{-- ───────────────────────────────────────────────────── LES ENTREPRISES --}}
<section id="b2b" class="cx-arg cx-arg--pose scroll-mt-20 py-24 sm:py-28" aria-labelledby="cx-arg-b2b">
    <div class="mx-auto max-w-7xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <p class="cx-subhead">{{ $a('entreprises.surtitre') }}</p>
            <h3 id="cx-arg-b2b" class="cx-arg__titre cx-headline cx-balance">{{ $a('entreprises.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('entreprises.sous_titre') }}</p>
        </div>

        <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($liste('entreprises.cartes') as $i => $carte)
                <article class="cx-arg__carte cx-arg__carte--compacte" data-cx-reveal data-cx-delay="{{ $i * 60 }}">
                    <h4 class="cx-arg__carte-titre">{{ $carte['titre'] }}</h4>
                    @foreach ($carte['textes'] as $texte)
                        <p class="cx-arg__texte">{{ __($texte, $chiffres) }}</p>
                    @endforeach
                </article>
            @endforeach
        </div>

        <p class="cx-arg__note cx-arg__note--large">{{ $a('entreprises.note') }}</p>

        <div class="mt-8 text-center">
            <x-ui.button :href="$versInscription" variant="outline" size="lg" icon="arrow-right" iconPosition="right">
                {{ $a('entreprises.bouton') }}
            </x-ui.button>
        </div>
    </div>
</section>

{{-- ────────────────────────────────────────── LES SIX QUESTIONS GÊNANTES --}}
<section class="cx-arg py-24 sm:py-28" aria-labelledby="cx-arg-questions">
    <div class="mx-auto max-w-4xl px-6">
        <div class="text-center">
            <h3 id="cx-arg-questions" class="cx-arg__titre cx-headline cx-balance">{{ $a('questions.titre') }}</h3>
            <p class="cx-arg__chapeau cx-body-readable">{{ $a('questions.sous_titre') }}</p>
        </div>

        <div class="mt-12 space-y-3">
            @foreach ($liste('questions.items') as $i => $item)
                <details class="cx-arg__question" data-cx-reveal data-cx-delay="{{ $i * 60 }}">
                    <summary>
                        <span>{{ $item['q'] }}</span>
                        <span class="cx-arg__croix" aria-hidden="true">+</span>
                    </summary>
                    <p class="cx-arg__texte">{{ __($item['r'], $chiffres) }}</p>
                </details>
            @endforeach
        </div>

        <p class="cx-arg__position cx-arg__position--fin">{{ $a('position') }}</p>
    </div>
</section>
