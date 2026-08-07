<x-guest-layout>
    {{-- ============================================================
         Brio — Landing page (refonte Stripe/Linear-light, 21/05/2026)
         Direction : sérieux SaaS multi-métiers B2B + B2C.
         Clean, dense info, trust signals.
         ============================================================ --}}
    {{-- Note: no <img> tags here — all visuals use CSS backgrounds/gradients.
         loading="lazy" is applied automatically where <img> tags are added.
         OG image preloaded via <link rel="preload"> in layouts/guest.blade.php. --}}

    {{-- ============================================================
         HERO — Luxury cinématique (dark, fullscreen).
         WebGL (Three.js) + GSAP + Motion ; composants réutilisables dans
         resources/views/components/hero/*. Bundle JS poussé en bas de page.
         Parallaxe pointeur via le moteur partagé [data-cx-parallax] (app.js).
         ============================================================ --}}
    <x-hero.luxury scroll-target="#decouvrir" scroll-label="Découvrir">
        <x-slot:eyebrow>
            <x-hero.eyebrow>Marketplace multi-métiers • B2C &amp; B2B</x-hero.eyebrow>
        </x-slot:eyebrow>

        <x-slot:title>
            <span class="cx-line"><span>L'excellence des services pros,</span></span>
            <span class="cx-line"><span class="cx-lux-serif cx-lux-gradient-ink">à la demande.</span></span>
        </x-slot:title>

        Nettoyage, peinture, plomberie, jardinage, babysitting — un seul compte, un prestataire vérifié près de chez vous en quelques clics. Devis IA depuis photo, paiement sécurisé Stripe, satisfaction garantie.

        <x-slot:actions>
            <span class="cx-magnetic" data-cx-magnetic="0.3">
                <x-ui.button href="{{ route('booking.create') }}" variant="amber" size="xl" icon="arrow-right" iconPosition="right" class="brio-glow-amber cx-cta-primary">
                    Réserver une mission
                </x-ui.button>
            </span>
            @if (Route::has('providers.browse.public'))
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="{{ route('providers.browse.public') }}" class="cx-lux-btn-ghost">
                        <x-ui.icon name="magnifying-glass" class="w-5 h-5" />
                        Trouver un prestataire
                    </a>
                </span>
            @endif
            {{--
                DEUX ENTRÉES AJOUTÉES LE 2026-08-05, parce qu'elles n'existaient nulle part.
                Un parcours d'accessibilité a montré que cette page ne citait que quatre routes
                — booking.create, login, register, providers.browse.public — et ne menait donc
                NI au moteur de commande (`order.journey` : secteur → métier → questions), NI aux
                pages services publiques. Les deux ensembles ne se citaient qu'entre eux : des
                boucles fermées, sans porte d'entrée depuis le site.

                Le bouton principal reste `booking.create` : le remplacer par le moteur de commande
                est une décision produit, pas une correction d'accessibilité. Ces liens ouvrent
                l'accès sans arbitrer entre les deux parcours.
            --}}
            @if (Route::has('order.journey'))
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="{{ route('order.journey') }}" class="cx-lux-btn-ghost">
                        <x-ui.icon name="bolt" class="w-5 h-5" />
                        Commander en quelques questions
                    </a>
                </span>
            @endif
            @if (Route::has('services.index'))
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="{{ route('services.index') }}" class="cx-lux-btn-ghost">
                        <x-ui.icon name="cube" class="w-5 h-5" />
                        Tous nos services
                    </a>
                </span>
            @endif
        </x-slot:actions>

        <x-slot:trust>
            <x-ui.icon name="shield-check" class="w-4 h-4" style="color: var(--cx-amber)" />
            KYC validé
            <span class="cx-lux-trust__sep">·</span> Stripe Connect
            <span class="cx-lux-trust__sep">·</span> Assurance incluse
        </x-slot:trust>

        {{-- Puce d'avis compacte : visible seulement < lg (cf. .cx-lux-proof). --}}
        <x-slot:proof>
            <span class="cx-lux-proof__chip">
                <span class="cx-lux-stars">★★★★★</span>
                <strong>4.8</strong>
                <span class="cx-lux-proof__sub">12 480 avis vérifiés</span>
            </span>
        </x-slot:proof>

        <x-slot:media>
            {{-- Floating mock-UI glass cards (procedural, no copyrighted assets). --}}
            <x-hero.floating-card tone="amber" icon="star"
                position="top: 1rem; left: 0; right: auto;" delay="0s">
                <p class="cx-lux-card__label">Note moyenne</p>
                <p class="cx-lux-card__value">4.8 <span class="cx-lux-stars">★★★★★</span></p>
                <p class="cx-lux-card__sub">12 480 avis vérifiés</p>
            </x-hero.floating-card>

            <x-hero.floating-card tone="cyan" icon="map-pin"
                position="top: 8.5rem; right: 0;" delay="-2.5s">
                <div class="flex items-center gap-2">
                    <span class="cx-lux-dot"></span>
                    <p class="cx-lux-card__value">Prestataire en route</p>
                </div>
                <p class="cx-lux-card__sub">Plomberie · Bruxelles · ETA 8 min</p>
            </x-hero.floating-card>

            <x-hero.floating-card tone="violet" icon="sparkles"
                position="bottom: 1.5rem; left: 1.5rem;" delay="-4.5s">
                <p class="cx-lux-card__label">Devis IA depuis photo</p>
                <p class="cx-lux-card__value">≈ 320 € <span class="cx-lux-card__sub" style="margin:0">en 10 s</span></p>
            </x-hero.floating-card>
        </x-slot:media>
    </x-hero.luxury>

    {{-- TRUST BAR --}}
    <section id="decouvrir" class="border-y border-slate-100 bg-slate-50/50 py-8 scroll-mt-20">
        <div class="mx-auto max-w-7xl px-6">
            <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-500" data-cx-reveal>
                30+ métiers · 6 langues · Conformité RGPD + Factur-X EU
            </p>
            <div class="mt-6 grid grid-cols-2 gap-6 sm:grid-cols-4 lg:grid-cols-6">
                @foreach (['Nettoyage', 'Peinture', 'Plomberie', 'Jardinage', 'Babysitting', 'Toiture'] as $i => $trade)
                    <div class="cx-trade-pill flex items-center justify-center text-sm font-medium text-slate-600" data-cx-reveal data-cx-delay="{{ $i + 1 }}">
                        {{ $trade }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FEATURES SHOWCASE --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="cx-subhead">Comment ça marche</span>
                <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl cx-headline cx-balance">
                    Trouvez. Réservez. C'est fait.
                </h2>
                <p class="mt-4 text-base text-slate-600 cx-body-readable mx-auto">
                    Plus de devis interminables, plus de no-shows. Notre IA estime, notre matching trouve, notre prestataire intervient — tracking GPS en temps réel.
                </p>
            </div>

            <div class="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-6 md:grid-cols-3">
                {{-- Feature 1: spans 2 columns, larger, amber border-left accent --}}
                <div class="md:col-span-2 brio-glass cx-lift cx-tilt rounded-2xl p-8 border-l-4 border-accent-amber" data-cx-reveal data-cx-tilt="5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <x-ui.icon name="camera" class="w-6 h-6" />
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-slate-900">Devis IA depuis photo</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 cx-body-readable">
                        Prenez une photo, choisissez le métier — recevez une fourchette de prix en 10 secondes, sans appel commercial.
                    </p>
                </div>

                {{-- Feature 2: single column --}}
                <div class="brio-glass cx-lift cx-tilt rounded-2xl p-6" data-cx-reveal data-cx-delay="100" data-cx-tilt="6">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
                        <x-ui.icon name="badge-check" class="w-6 h-6" />
                    </div>
                    <h3 class="mt-5 text-base font-semibold text-slate-900">Prestataires vérifiés KYC</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Identité contrôlée Onfido/Veriff, casier judiciaire vérifié, assurance RC pro incluse sur chaque mission.
                    </p>
                </div>

                {{-- Feature 3: full width, horizontal layout --}}
                <div class="md:col-span-3 brio-glass cx-lift rounded-2xl p-6 flex flex-col md:flex-row md:items-center gap-6" data-cx-reveal data-cx-delay="200">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-600 ring-1 ring-purple-200">
                        <x-ui.icon name="map-pin" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Tracking GPS en direct</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-600">
                            Suivez l'arrivée de votre prestataire sur carte, ETA en temps réel, géofence d'arrivée auto. Votre prestataire est déjà en route — vous le savez avant même qu'il sonne.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================================
         MÉTIERS — galerie horizontale épinglée (premium-scroll engine).
         [data-scroll-horizontal] : pin + scale/translateX/fade par panneau
         sur desktop ; scroll natif tactile (snap + inertie) sur mobile.
         PAS de [data-premium-scroll] ici -> on n'active PAS Lenis global :
         le moteur ne fait QUE cette section, son cleanup est isolé via
         gsap.matchMedia et ne touche pas le ScrollTrigger 3D du parcours.
         id="metiers" : cible l'ancre de nav « Métiers » (était orpheline).
         ============================================================ --}}
    <section id="metiers" class="scroll-mt-20" data-scroll-horizontal aria-label="Nos métiers à la demande">
        <div data-scroll-track>
            {{-- Intro --}}
            <article class="flex items-center justify-center bg-white" data-scroll-panel>
                <div class="mx-auto max-w-xl px-6 text-center" data-scroll-panel-inner>
                    <span class="cx-subhead">Nos métiers</span>
                    <h2 class="mt-2 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl cx-headline cx-balance">
                        30+ métiers.<br><span class="text-brand-600">Un seul compte.</span>
                    </h2>
                    <p class="mt-5 text-base text-slate-600 cx-body-readable mx-auto">
                        Du ménage hebdomadaire à la rénovation complète — un prestataire vérifié pour chaque besoin, près de chez vous.
                    </p>
                    <p class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-slate-400">
                        Faites défiler <span aria-hidden="true">→</span>
                    </p>
                </div>
            </article>

            {{-- Maison & ménage --}}
            <article class="flex items-center justify-center bg-brand-50" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-brand-600 ring-1 ring-brand-200 shadow-soft-md">
                        <x-ui.icon name="sparkles" class="w-8 h-8" />
                    </div>
                    <h3 class="mt-6 text-2xl font-bold text-slate-900">Maison &amp; ménage</h3>
                    <p class="mt-3 text-base text-slate-600">Nettoyage régulier, grand ménage, repassage, vitres, désinfection.</p>
                    <p class="mt-5 text-sm font-semibold text-brand-600">Nettoyage · Repassage · Vitres · Désinfection</p>
                </div>
            </article>

            {{-- Travaux & rénovation --}}
            <article class="flex items-center justify-center bg-amber-50" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-amber-600 ring-1 ring-amber-200 shadow-soft-md">
                        <x-ui.icon name="wrench" class="w-8 h-8" />
                    </div>
                    <h3 class="mt-6 text-2xl font-bold text-slate-900">Travaux &amp; rénovation</h3>
                    <p class="mt-3 text-base text-slate-600">Peinture, plomberie, carrelage, menuiserie — orchestrés dans le bon ordre.</p>
                    <p class="mt-5 text-sm font-semibold text-amber-600">Peinture · Plomberie · Carrelage · Menuiserie</p>
                </div>
            </article>

            {{-- Énergie & confort --}}
            <article class="flex items-center justify-center bg-emerald-50" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-emerald-600 ring-1 ring-emerald-200 shadow-soft-md">
                        <x-ui.icon name="bolt" class="w-8 h-8" />
                    </div>
                    <h3 class="mt-6 text-2xl font-bold text-slate-900">Énergie &amp; confort</h3>
                    <p class="mt-3 text-base text-slate-600">Électricité, domotique, chauffage, bornes de recharge — par des pros assurés.</p>
                    <p class="mt-5 text-sm font-semibold text-emerald-600">Électricité · Domotique · Chauffage · Bornes</p>
                </div>
            </article>

            {{-- CTA --}}
            <article class="flex items-center justify-center bg-brand-600" data-scroll-panel>
                <div class="mx-auto max-w-md px-6 text-center" data-scroll-panel-inner>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/20">
                        <x-ui.icon name="star" class="w-8 h-8" />
                    </div>
                    <h3 class="mt-6 text-3xl font-bold text-white sm:text-4xl">Et bien plus encore.</h3>
                    <p class="mt-4 text-base text-white/80">Babysitting, jardinage, toiture, déménagement, montage de meubles…</p>
                    <div class="mt-8">
                        <x-ui.button href="{{ route('booking.create') }}" variant="amber" icon="arrow-right" iconPosition="right">
                            Réserver une mission
                        </x-ui.button>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- DIFFERENTIATING SECTION : multi-trades bundle --}}
    <section class="bg-slate-50/40 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div data-cx-reveal>
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Différenciateur</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Plusieurs métiers ?<br>
                        <span class="text-brand-600">Un seul chantier groupé.</span>
                    </h2>
                    <p class="mt-6 text-base leading-7 text-slate-600">
                        Rénovation salle de bain ? Carreleur + plombier + peintre + électricien — orchestrés dans le bon ordre, prix groupé, facture consolidée, 1 seul interlocuteur. <strong>Aucun concurrent ne fait ça.</strong>
                    </p>
                    <ul class="mt-8 space-y-3 text-sm text-slate-700">
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Dépendances cascade (carreleur avant peintre)
                        </li>
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Discount groupage automatique
                        </li>
                        <li class="flex gap-3">
                            <x-ui.icon name="check" class="w-5 h-5 text-emerald-500 flex-shrink-0" />
                            Suivi unifié & facturation consolidée
                        </li>
                    </ul>
                    <div class="mt-8">
                        <x-ui.button href="{{ route('booking.create') }}" variant="outline" icon="arrow-right" iconPosition="right">
                            Démarrer un chantier groupé
                        </x-ui.button>
                    </div>
                </div>

                <div class="relative" data-cx-reveal data-cx-delay="100">
                    <div class="cx-float overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft-md">
                        <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3">
                            <p class="text-xs font-mono text-slate-500">bundle_demo_renovation</p>
                            <p class="text-sm font-semibold text-slate-900">Rénovation salle de bain</p>
                        </div>
                        <div class="divide-y divide-slate-100 text-sm">
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">1.</span>
                                    <x-ui.icon name="wrench" class="w-4 h-4 text-brand-600" />
                                    <span class="text-slate-700"><strong>Plombier</strong> — dépose ancienne robinetterie</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">320€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">2.</span>
                                    <x-ui.icon name="cube" class="w-4 h-4 text-amber-600" />
                                    <span class="text-slate-700"><strong>Carreleur</strong> — pose carrelage sol + mur</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">1 240€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">3.</span>
                                    <x-ui.icon name="sparkles" class="w-4 h-4 text-purple-600" />
                                    <span class="text-slate-700"><strong>Peintre</strong> — plafond + finitions</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">480€</span>
                            </div>
                            <div class="flex items-center justify-between px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-xs text-slate-400">4.</span>
                                    <x-ui.icon name="bolt" class="w-4 h-4 text-yellow-600" />
                                    <span class="text-slate-700"><strong>Électricien</strong> — éclairage LED + miroir</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">220€</span>
                            </div>
                        </div>
                        <div class="bg-emerald-50/50 px-5 py-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total avec groupage</span>
                                <span class="text-lg font-bold text-emerald-700">2 084€ <span class="text-xs font-medium line-through text-slate-400 ml-2">2 260€</span></span>
                            </div>
                            <p class="mt-1 text-xs text-emerald-600">Économie groupage : 176€ (−8%)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- B2B SECTION --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <span class="cx-subhead">Pour les entreprises</span>
                <h2 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl cx-headline cx-balance">
                    Facility management, simplifié.
                </h2>
                <p class="mt-4 text-base text-slate-600 cx-body-readable mx-auto">
                    API Tokens, webhooks B2B HMAC, multi-sites, bulk booking CSV, factures Peppol/Factur-X 09/2026-ready. Conçu pour scaler de 5 à 5000 sites.
                </p>
            </div>
            <div class="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-6 md:grid-cols-3">
                {{-- B2B card 1: full-width top, horizontal layout --}}
                <div class="md:col-span-3 brio-glass cx-lift rounded-2xl p-8 border-l-4 border-accent-amber flex flex-col md:flex-row md:items-center gap-6" data-cx-reveal data-cx-delay="0">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <x-ui.icon name="building-office" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Multi-sites & multi-membres</h3>
                        <p class="mt-1 text-sm text-slate-600">Plusieurs locaux, équipe avec rôles personnalisés, validation hiérarchique. De 5 à 5 000 sites — la plateforme scale avec vous.</p>
                    </div>
                </div>
                {{-- B2B card 2: spans 2 columns --}}
                <div class="md:col-span-2 brio-glass cx-lift cx-tilt rounded-2xl p-6" data-cx-reveal data-cx-delay="100" data-cx-tilt="5">
                    <x-ui.icon name="receipt" class="w-6 h-6 text-brand-600" />
                    <h3 class="mt-4 font-semibold text-slate-900">Facturation Peppol</h3>
                    <p class="mt-2 text-sm text-slate-600">Factur-X XML CII embedded, conformité réglementation 09/2026 FR. Export FEC DGFiP/Sage/QuickBooks.</p>
                </div>
                {{-- B2B card 3: single column --}}
                <div class="brio-glass cx-lift cx-tilt rounded-2xl p-6" data-cx-reveal data-cx-delay="200" data-cx-tilt="6">
                    <x-ui.icon name="key" class="w-6 h-6 text-brand-600" />
                    <h3 class="mt-4 font-semibold text-slate-900">API + Webhooks HMAC</h3>
                    <p class="mt-2 text-sm text-slate-600">18 scopes, rotation tokens, webhooks signés HMAC SHA256 retry exponentiel.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PARCOURS D'UNE MISSION (scrollytelling cinématique) --}}
    @include('partials.journey')

    {{-- SOCIAL PROOF / METRICS --}}
    <section class="py-20 bg-gradient-to-b from-white to-slate-50" data-cx-reveal>
        <div class="max-w-6xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-center text-slate-900 mb-4" style="font-family: 'Space Grotesk', sans-serif;">
                La confiance de nos utilisateurs
            </h2>
            <p class="text-center text-slate-500 mb-12">Rejoignez une communauté qui grandit chaque jour</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center" data-cx-reveal data-cx-delay="0">
                    <p class="text-4xl font-bold text-accent-amber cx-tabular" style="font-family: 'Space Grotesk', sans-serif;" data-cx-count="20" data-cx-suffix="+">20+</p>
                    <p class="text-sm text-slate-500 mt-2">Métiers disponibles</p>
                </div>
                <div class="text-center" data-cx-reveal data-cx-delay="100">
                    <p class="text-4xl font-bold text-accent-amber cx-tabular" style="font-family: 'Space Grotesk', sans-serif;" data-cx-count="9">9</p>
                    <p class="text-sm text-slate-500 mt-2">Pays supportés</p>
                </div>
                <div class="text-center" data-cx-reveal data-cx-delay="200">
                    <p class="text-4xl font-bold text-accent-amber cx-tabular" style="font-family: 'Space Grotesk', sans-serif;" data-cx-count="4.8" data-cx-suffix="★">4.8★</p>
                    <p class="text-sm text-slate-500 mt-2">Note moyenne</p>
                </div>
                <div class="text-center" data-cx-reveal data-cx-delay="300">
                    <p class="text-4xl font-bold text-accent-amber cx-tabular" style="font-family: 'Space Grotesk', sans-serif;">24/7</p>
                    <p class="text-sm text-slate-500 mt-2">Support disponible</p>
                </div>
            </div>
        </div>
    </section>

    {{-- TESTIMONIALS --}}
    <section class="py-20 bg-white" data-cx-reveal>
        <div class="max-w-6xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-center text-slate-900 mb-12 cx-headline" style="font-family: 'Space Grotesk', sans-serif;">
                Ce qu'ils en disent
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                {{-- Featured testimonial: spans 3 columns --}}
                <div class="md:col-span-3 brio-glass cx-lift rounded-2xl p-8" data-cx-reveal>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-xl">M</div>
                        <div>
                            <p class="font-semibold text-slate-900 text-lg">Marie D.</p>
                            <p class="text-sm text-slate-500">Bruxelles • Cliente depuis 2024</p>
                        </div>
                    </div>
                    <blockquote class="text-slate-700 text-lg leading-relaxed italic cx-body-readable">
                        "Réservation en 2 minutes, prestataire ponctuel, paiement transparent. Je ne cherche plus ailleurs."
                    </blockquote>
                    <p class="text-accent-amber mt-4 text-lg">★★★★★</p>
                </div>
                {{-- Side column: 2 smaller testimonials stacked --}}
                <div class="md:col-span-2 flex flex-col gap-6">
                    <div class="brio-glass cx-lift rounded-2xl p-6 flex-1" data-cx-reveal data-cx-delay="100">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-700 font-bold">A</div>
                            <div>
                                <p class="font-semibold text-slate-900">Ahmed K.</p>
                                <p class="text-xs text-slate-500">Paris • Prestataire</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600">"Grâce à Brio, j'ai triplé mes missions mensuelles. L'app terrain est intuitive et les paiements arrivent vite."</p>
                        <p class="text-accent-amber mt-3 text-sm">★★★★★</p>
                    </div>
                    <div class="brio-glass cx-lift rounded-2xl p-6 flex-1" data-cx-reveal data-cx-delay="200">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-brand-50 flex items-center justify-center text-brand-600 font-bold">S</div>
                            <div>
                                <p class="font-semibold text-slate-900">Sophie L.</p>
                                <p class="text-xs text-slate-500">Liège • Gestionnaire multi-sites</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600">"Gérer 12 bureaux avec un seul tableau de bord, c'est un gain de temps énorme. Le chantier groupé est brillant."</p>
                        <p class="text-accent-amber mt-3 text-sm">★★★★☆</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-20 bg-slate-50" data-cx-reveal>
        <div class="max-w-5xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-center text-slate-900 mb-12 cx-headline" style="font-family: 'Space Grotesk', sans-serif;">
                Questions fréquentes
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach([
                    ['Comment fonctionne Brio ?', 'Choisissez un service, précisez vos besoins, et un prestataire vérifié vous est attribué automatiquement. Suivez la mission en temps réel et payez en toute sécurité.'],
                    ['Combien ça coûte ?', 'Le prix dépend du service et de la durée estimée. Vous recevez un devis avant de confirmer — pas de surprise. Vous pouvez même obtenir un devis instantané par photo grâce à notre IA.'],
                    ['Les prestataires sont-ils vérifiés ?', "Oui. Chaque prestataire passe une vérification d'identité (KYC), fournit ses certifications professionnelles et est noté après chaque mission."],
                    ['Comment devenir prestataire ?', "Téléchargez l'app Brio Provider, inscrivez-vous, complétez la vérification et commencez à recevoir des missions. Paiements directs sur votre compte via Stripe."],
                    ['Dans quels pays est disponible Brio ?', 'Belgique, France, Pays-Bas, Allemagne, Espagne, Italie, Portugal, Luxembourg et Autriche. Nous étendons notre couverture régulièrement.'],
                    ['Puis-je annuler une réservation ?', 'Oui, avec des conditions selon le délai. Annulation gratuite > 24h avant. Des frais peuvent s\'appliquer dans les 24h précédant la mission.'],
                ] as [$question, $answer])
                    <details class="brio-glass cx-lift rounded-xl p-5 group cursor-pointer" data-cx-reveal>
                        <summary class="font-semibold text-slate-900 list-none flex justify-between items-center">
                            {{ $question }}
                            <span class="text-brand-500 group-open:rotate-45 transition-transform">+</span>
                        </summary>
                        <p class="text-sm text-slate-600 mt-3 leading-relaxed">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA FINAL --}}
    <section class="cx-cta-animated relative isolate overflow-hidden py-24">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Prêt à essayer ?
            </h2>
            <p class="mt-4 text-base leading-7 text-brand-100">
                Inscription en 30 secondes. Aucune carte requise pour explorer.
            </p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4">
                <span class="cx-magnetic" data-cx-magnetic="0.3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-semibold text-brand-700 shadow-soft-md hover:bg-brand-50 transition">
                        Créer un compte gratuit
                        <x-ui.icon name="arrow-right" class="w-5 h-5" />
                    </a>
                </span>
                <span class="cx-magnetic" data-cx-magnetic="0.22">
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-6 py-3 text-base font-semibold text-white backdrop-blur hover:bg-white/20 transition">
                        Se connecter
                    </a>
                </span>
            </div>
        </div>
    </section>

    @push('scripts')
        @vite('resources/js/home-journey.js')
        @vite('resources/js/luxury-hero.js')
        @vite('resources/js/hero-r3f.jsx')
    @endpush
</x-guest-layout>
