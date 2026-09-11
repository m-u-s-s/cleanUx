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
        $versCommande = Route::has('order.journey')? route('order.journey'): route('booking.create');
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

    {{-- L'ACCROCHE, reprise mot pour mot du hero : elle tient la page. --}}
    <section class="cx-arg border-y py-10" style="border-color: var(--cx-arg-ligne)">
        <div class="mx-auto max-w-7xl px-6">
            <ul class="mx-auto grid max-w-4xl gap-3 sm:grid-cols-3">
                @foreach ((array) __('vitrine.accueil.hero.puces') as $i => $puce)
                    <li class="flex gap-2 text-sm" data-cx-reveal data-cx-delay="{{ $i * 80 }}"
                        style="color: var(--cx-arg-doux)">
                        <x-ui.icon name="check" class="cx-arg__coche" />
                        <span>{{ $puce }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- MÉTIERS — galerie horizontale épinglée (moteur premium-scroll).
         [data-scroll-horizontal] : pin + scale/translateX/fade par panneau sur desktop ;
         scroll natif tactile sur mobile. PAS de [data-premium-scroll] : on n'active pas
         Lenis global, le cleanup reste isolé du ScrollTrigger du film. --}}
    <section id="metiers" class="scroll-mt-20" data-scroll-horizontal aria-label="Les métiers ouverts">
        <div data-scroll-track>
            <article class="flex items-center justify-center bg-white dark:bg-slate-900" data-scroll-panel>
                <div class="mx-auto max-w-xl px-6 text-center" data-scroll-panel-inner>
                    <span class="cx-subhead">Ce que vous pouvez commander</span>
                    <h3 class="mt-2 text-4xl font-bold tracking-tight text-slate-900 dark:text-slate-100 cx-headline cx-balance">
                        {{ \App\Support\Vitrine\ChiffresDeLaVitrine::metiersOuverts() }} métiers.<br><span class="text-brand-600 dark:text-brand-300">Six secteurs.</span>
                    </h3>
                    <p class="mt-5 text-base text-slate-600 dark:text-slate-400 cx-body-readable mx-auto">
                        Ce chiffre est celui du catalogue, lu au moment où vous chargez cette page. Il monte quand un métier ouvre, et il descend quand un métier ferme.
                    </p>
                    <p class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-slate-400">
                        Faites défiler <span aria-hidden="true">→</span>
                    </p>
                </div>
            </article>

            <article class="flex items-center justify-center bg-brand-50 dark:bg-slate-800" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-brand-600 ring-1 ring-brand-200 shadow-soft-md">
                        <x-ui.icon name="sparkles" class="w-8 h-8" />
                    </div>
                    <h4 class="mt-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Maison &amp; ménage</h4>
                    <p class="mt-3 text-base text-slate-600 dark:text-slate-400">Nettoyage à domicile, vitres, fin de chantier.</p>
                    <p class="mt-5 text-sm font-semibold text-brand-600 dark:text-brand-300">Le nettoyage à domicile accepte l’intervention immédiate</p>
                </div>
            </article>

            <article class="flex items-center justify-center bg-amber-50 dark:bg-slate-800" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-amber-600 ring-1 ring-amber-200 shadow-soft-md">
                        <x-ui.icon name="wrench" class="w-8 h-8" />
                    </div>
                    <h4 class="mt-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Travaux &amp; rénovation</h4>
                    <p class="mt-3 text-base text-slate-600 dark:text-slate-400">Peinture, plomberie, bâtiment, rénovation.</p>
                    <p class="mt-5 text-sm font-semibold text-amber-700 dark:text-amber-300">Commandés ensemble, ils se suivent dans le bon ordre</p>
                </div>
            </article>

            <article class="flex items-center justify-center bg-emerald-50 dark:bg-slate-800" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-emerald-600 ring-1 ring-emerald-200 shadow-soft-md">
                        <x-ui.icon name="bolt" class="w-8 h-8" />
                    </div>
                    <h4 class="mt-6 text-2xl font-bold text-slate-900 dark:text-slate-100">Extérieur &amp; technique</h4>
                    <p class="mt-3 text-base text-slate-600 dark:text-slate-400">Électricité, jardinage, toiture, élagage.</p>
                    <p class="mt-5 text-sm font-semibold text-emerald-700 dark:text-emerald-300">La toiture et l’élagage passent par un devis, et le disent d’entrée</p>
                </div>
            </article>

            <article class="flex items-center justify-center bg-brand-600" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/20">
                        <x-ui.icon name="cube" class="w-8 h-8" />
                    </div>
                    <h4 class="mt-6 text-3xl font-bold text-white sm:text-4xl">Et le reste du catalogue.</h4>
                    <p class="mt-4 text-base text-white/80">Garde d’enfants, déménagement, levage, gardiennage, courses d’un point à un autre.</p>
                    <div class="mt-8">
                        <x-ui.button :href="$versCommande" variant="amber" icon="arrow-right" iconPosition="right">
                            {{ $h('bouton') }}
                        </x-ui.button>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- PARCOURS D'UNE MISSION (le film, scrollytelling cinématique) --}}
    @include('partials.journey')

    {{-- L'ARGUMENTAIRE : deux portes (je commande / je gagne), puis ce qui protège
         les deux côtés, les entreprises, et quatre questions. --}}
    @include('partials.accueil-argumentaire')

    {{-- CTA FINAL --}}
    <section class="cx-cta-animated relative isolate overflow-hidden py-24">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <h3 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                {{ __('vitrine.accueil.final.titre') }}
            </h3>
            <p class="mt-4 text-base leading-7 text-brand-100">
                {{ __('vitrine.accueil.final.sous_titre') }}
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                <span class="cx-magnetic" data-cx-magnetic="0.3">
                    <a href="{{ $versCommande }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-soft-md hover:bg-brand-50 transition">
                        {{ $h('bouton') }}
                        <x-ui.icon name="arrow-right" class="w-5 h-5" />
                    </a>
                </span>
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="#commencer"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 text-base font-semibold text-white backdrop-blur hover:bg-white/20 transition">
                        {{ __('vitrine.accueil.final.bouton_secondaire') }}
                    </a>
                </span>
            </div>
        </div>
    </section>

    @push('scripts')
        @vite('resources/js/journey-film.js')
        @vite('resources/js/luxury-hero.js')
        @vite('resources/js/hero-r3f.jsx')
    @endpush
</x-guest-layout>
