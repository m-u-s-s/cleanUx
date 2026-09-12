<x-guest-layout :cta="true">
    {{-- ============================================================
         Brio — l'accueil.

         REFAITE LE 2026-09-11. La version précédente annonçait « 30+ métiers »,
         « 9 pays supportés », « 12 480 avis vérifiés », « 4.8 », « assurance RC pro
         incluse », « identité contrôlée Onfido/Veriff » et « satisfaction garantie ».
         Mesuré dans le code : seize métiers, un seul pays ouvert, aucune table d'avis
         alimentée, aucun assureur branché et un pilote d'identité simulé.

         Le texte vit dans lang/*/vitrine.php sous « accueil » ; les chiffres se lisent
         dans le moteur au rendu (App\Support\Vitrine\ChiffresDeLaVitrine).
         ============================================================ --}}
    @php
        $chiffresHero = [
            'metiers' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersOuverts(),
            'immediat' => \App\Support\Vitrine\ChiffresDeLaVitrine::metiersEnImmediat(),
            'zones' => \App\Support\Vitrine\ChiffresDeLaVitrine::zonesOuvertes(),
        ];
        $h = fn (string $cle) => __('vitrine.accueil.hero.'.$cle, $chiffresHero);
        $versCommande = Route::has('order.journey') ? route('order.journey') : route('booking.create');
        $versInscription = route('register');

        // Les textes du carrousel partent au navigateur : la bascule les echange sans
        // recharger la page, et ils restent traduisibles depuis /admin/traductions.
        $textesDuCarrousel = ['metiers' => []];
        foreach (['client', 'prestataire'] as $cote) {
            $textesDuCarrousel['metiers'][$cote] = [
                'surtitre' => __('vitrine.accueil.metiers.'.$cote.'.surtitre'),
                'titre' => __('vitrine.accueil.metiers.'.$cote.'.titre', $chiffresHero),
                'texte' => __('vitrine.accueil.metiers.'.$cote.'.texte', $chiffresHero),
                'finTitre' => __('vitrine.accueil.metiers.'.$cote.'.fin_titre'),
                'finTexte' => __('vitrine.accueil.metiers.'.$cote.'.fin_texte'),
                'bouton' => __('vitrine.accueil.metiers.'.$cote.'.bouton'),
            ];
        }
    @endphp

    {{-- ============================================================
         HERO — Luxury cinématique (dark, fullscreen).
         WebGL (Three.js) + GSAP + Motion ; composants dans components/hero/*.
         ============================================================ --}}
    <x-hero.luxury scroll-target="#commencer" scroll-label="Découvrir">
        <x-slot:eyebrow>
            <x-brand.logo space="client" variant="dark" :size="72" class="mb-5" />
            <x-hero.eyebrow>Commandez un pro · Louez votre voiture · Louez votre logement</x-hero.eyebrow>
        </x-slot:eyebrow>

        <x-slot:title>
            <span class="cx-line"><span>Avec Brio,</span></span>
            <span class="cx-line"><span class="cx-lux-serif cx-lux-gradient-ink">tout le monde gagne de l’argent.</span></span>
        </x-slot:title>

        {{ $h('sous_titre') }}

        <x-slot:actions>
            <span class="cx-magnetic" data-cx-magnetic="0.3">
                <x-ui.button :href="$versCommande" variant="amber" size="xl" icon="arrow-right" iconPosition="right" class="brio-glow-amber cx-cta-primary">
                    {{ $h('bouton') }}
                </x-ui.button>
            </span>
            {{-- La seconde porte dès le premier écran : la moitié du public de cette
                 page vient pour gagner de l’argent, pas pour en dépenser. --}}
            <span class="cx-magnetic" data-cx-magnetic="0.22">
                <a href="#commencer" class="cx-lux-btn-ghost">
                    <x-ui.icon name="sparkles" class="w-5 h-5" />
                    {{ $h('bouton_secondaire') }}
                </a>
            </span>
            @if (Route::has('services.index'))
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="{{ route('services.index') }}" class="cx-lux-btn-ghost">
                        <x-ui.icon name="cube" class="w-5 h-5" />
                        Voir les métiers ouverts
                    </a>
                </span>
            @endif
        </x-slot:actions>

        {{-- La barre de confiance ne porte QUE des mécanismes vérifiables : ni note
             moyenne, ni assurance, ni nom de fournisseur d'identité. --}}
        <x-slot:trust>
            <x-ui.icon name="shield-check" class="w-4 h-4" style="color: var(--cx-amber)" />
            Le prix avant votre nom
            <span class="cx-lux-trust__sep">·</span> Un pro garde 85 %
            <span class="cx-lux-trust__sep">·</span> Inscription gratuite, sans abonnement
        </x-slot:trust>

        <x-slot:proof>
            <span class="cx-lux-proof__chip">
                <span class="cx-lux-proof__sub">{{ $h('note') }}</span>
            </span>
        </x-slot:proof>

        <x-slot:media>
            {{-- Trois cartes, trois mécanismes réels du moteur. --}}
            <x-hero.floating-card tone="amber" icon="sparkles"
                position="top: 1rem; left: 0; right: auto;" delay="0s">
                <p class="cx-lux-card__label">Votre estimation</p>
                <p class="cx-lux-card__value">280 € <span class="cx-lux-card__sub" style="margin:0">à 340 €</span></p>
                <p class="cx-lux-card__sub">C’est le bas qui vous engage</p>
            </x-hero.floating-card>

            <x-hero.floating-card tone="cyan" icon="map-pin"
                position="top: 8.5rem; right: 0;" delay="-2.5s">
                <div class="flex items-center gap-2">
                    <span class="cx-lux-dot"></span>
                    <p class="cx-lux-card__value">Un pro a accepté</p>
                </div>
                <p class="cx-lux-card__sub">Peinture · Bruxelles · sa position en direct</p>
            </x-hero.floating-card>

            <x-hero.floating-card tone="violet" icon="shield-check"
                position="bottom: 1.5rem; left: 1.5rem;" delay="-4.5s">
                <p class="cx-lux-card__label">Le code du démarrage</p>
                <p class="cx-lux-card__value">4 8 2 1 0 6</p>
                <p class="cx-lux-card__sub">C’est vous qui l’avez</p>
            </x-hero.floating-card>
        </x-slot:media>
    </x-hero.luxury>

    {{-- ============================================================
         TOUTE LA PAGE SOUS LE HÉROS BASCULE : côté client, ou côté prestataire.

         Le choix survit au rechargement et se partage par lien (#client,
         #prestataire) : un artisan à qui l'on envoie la page doit arriver
         sur son côté.

         LE FILM EST COMMUN AUX DEUX et reste hors de toute bascule : son
         moteur épingle la scène au défilement, et un ScrollTrigger monté
         dans un conteneur masqué mesure une hauteur nulle.
         ============================================================ --}}
    <div class="cx-accueil" x-data="cotesBrio()" x-init="demarrer()">

        @include('partials.accueil-bascule')

        {{-- MÉTIERS — galerie horizontale épinglée (moteur premium-scroll).
             UN SEUL slider, dont les textes basculent : deux sliders superposés
             feraient calculer le pin sur un panneau masqué, donc sur du vide. --}}
        <section id="metiers" class="scroll-mt-20" data-scroll-horizontal
                 aria-label="{{ __('vitrine.accueil.metiers.libelle') }}">
            <div data-scroll-track>
                <article class="cx-metier__panneau cx-metier__panneau--intro" data-scroll-panel>
                    <div class="mx-auto max-w-xl px-6 text-center" data-scroll-panel-inner>
                        <span class="cx-subhead" x-text="textes.metiers[cote].surtitre">{{ __('vitrine.accueil.metiers.client.surtitre') }}</span>
                        <h3 class="cx-metier__titre cx-headline cx-balance"
                            x-html="textes.metiers[cote].titre">{!! __('vitrine.accueil.metiers.client.titre', $chiffresHero) !!}</h3>
                        <p class="cx-metier__texte cx-body-readable mx-auto"
                           x-text="textes.metiers[cote].texte">{{ __('vitrine.accueil.metiers.client.texte', $chiffresHero) }}</p>
                        <p class="cx-metier__defiler">
                            {{ __('vitrine.accueil.metiers.defiler') }} <span aria-hidden="true">&rarr;</span>
                        </p>
                    </div>
                </article>

                @foreach ((array) __('vitrine.accueil.metiers.secteurs') as $i => $secteur)
                    <article class="cx-metier__panneau cx-metier__panneau--{{ $i + 1 }}" data-scroll-panel>
                        {{-- LE MÊME SECTEUR, DEUX REGARDS : le client voit le résultat chez lui,
                             le professionnel voit le chantier. Les deux images sont dans le DOM
                             et l'opacité les échange — les charger à la bascule ferait clignoter
                             le panneau au moment précis où on le regarde. --}}
                        @foreach (['client', 'prestataire'] as $vue)
                            <picture class="cx-metier__fond" :class="cote === '{{ $vue }}' && 'is-visible'"
                                     @if ($vue === 'client') data-defaut @endif aria-hidden="true">
                                <source srcset="{{ asset('images/accueil/secteurs/'.$secteur['fond'].'-'.($vue === 'client' ? 'client' : 'presta').'.avif') }}" type="image/avif">
                                <img src="{{ asset('images/accueil/secteurs/'.$secteur['fond'].'-'.($vue === 'client' ? 'client' : 'presta').'.webp') }}"
                                     alt="" width="1400" height="933" loading="lazy" decoding="async">
                            </picture>
                        @endforeach
                        <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                            <div class="cx-metier__pastille">
                                <x-ui.icon :name="$secteur['icone']" class="w-8 h-8" />
                            </div>
                            <h4 class="cx-metier__secteur">{{ $secteur['titre'] }}</h4>
                            <p class="cx-metier__liste">{{ $secteur['metiers'] }}</p>
                            @php($angles = ['client' => $secteur['client'], 'prestataire' => $secteur['prestataire']])
                            <p class="cx-metier__angle" x-text="{{ Js::from($angles) }}[cote]">
                                {{ $secteur['client'] }}
                            </p>
                        </div>
                    </article>
                @endforeach

                <article class="cx-metier__panneau cx-metier__panneau--fin" data-scroll-panel>
                    <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                        <div class="cx-metier__pastille cx-metier__pastille--fin">
                            <x-ui.icon name="cube" class="w-8 h-8" />
                        </div>
                        <h4 class="cx-metier__fin-titre" x-text="textes.metiers[cote].finTitre">
                            {{ __('vitrine.accueil.metiers.client.fin_titre') }}
                        </h4>
                        <p class="cx-metier__fin-texte" x-text="textes.metiers[cote].finTexte">
                            {{ __('vitrine.accueil.metiers.client.fin_texte') }}
                        </p>
                        <div class="mt-8">
                            @php($cibles = ['client' => $versCommande, 'prestataire' => $versInscription])
                            <span class="cx-magnetic" data-cx-magnetic="0.26">
                            <a class="cx-metier__bouton" href="{{ $versCommande }}"
                               :href="{{ Js::from($cibles) }}[cote]"
                               x-text="textes.metiers[cote].bouton">{{ $h('bouton') }}</a>
                            </span>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        {{-- LE FILM — commun aux deux côtés, hors de toute bascule. --}}
        @include('partials.journey')

        {{-- LES DEUX CÔTÉS. --}}
        <div id="cx-cote-client" role="tabpanel" aria-labelledby="cx-bascule-client"
             x-show="cote === 'client'">
            @include('partials.accueil-cote', ['cote' => 'client'])
        </div>

        <div id="cx-cote-prestataire" role="tabpanel" aria-labelledby="cx-bascule-prestataire"
             x-show="cote === 'prestataire'" x-cloak>
            @include('partials.accueil-cote', ['cote' => 'prestataire'])
        </div>
    </div>

    @push('scripts')
        {{-- Les textes du carrousel passent par ici : ils doivent rester traduisibles
             depuis /admin/traductions, donc ils ne peuvent pas vivre dans le module JS. --}}
        <script>
            window.brioTextes = {!! Js::from($textesDuCarrousel) !!};
        </script>
        @vite('resources/js/accueil-cotes.js')
        @vite('resources/js/journey-film.js')
        @vite('resources/js/luxury-hero.js')
        @vite('resources/js/hero-r3f.jsx')
    @endpush
</x-guest-layout>
