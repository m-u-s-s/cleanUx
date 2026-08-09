<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <x-brand.head space="client" />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO — title + description (page-overridable via $seoTitle / $seoDescription) --}}
    <title>{{ $seoTitle ?? config('app.name', 'Brio') . ' — Services pros à la demande | Nettoyage, Peinture, Babysitting' }}</title>
    <meta name="description" content="{{ $seoDescription ?? 'Réservez un professionnel vérifié en 2 minutes. 30+ métiers disponibles en Belgique. Paiement sécurisé, assurance incluse, suivi en temps réel.' }}">
    <meta name="keywords" content="{{ $seoKeywords ?? 'services à domicile, nettoyage, peinture, babysitting, Belgique, réservation en ligne, prestataire vérifié' }}">
    <meta name="robots" content="index,follow">

    {{-- Canonical + hreflang --}}
    <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">
    <link rel="alternate" hreflang="fr-BE" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="nl-BE" href="{{ str_replace('/fr/', '/nl/', url()->current()) }}">
    <link rel="alternate" hreflang="x-default" href="{{ config('app.url') }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'Brio') }}">
    <meta property="og:title" content="{{ $seoTitle ?? config('app.name', 'Brio') . ' — Services pros à la demande' }}">
    <meta property="og:description" content="{{ $seoDescription ?? 'Réservez un professionnel vérifié en 2 minutes. 30+ métiers en Belgique.' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-brio.svg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:locale" content="fr_BE">
    <meta property="og:locale:alternate" content="nl_BE">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle ?? config('app.name', 'Brio') . ' — Services pros à la demande' }}">
    <meta name="twitter:description" content="{{ $seoDescription ?? 'Réservez un professionnel vérifié en 2 minutes.' }}">
    <meta name="twitter:image" content="{{ asset('images/og-brio.svg') }}">

    {{-- JSON-LD Structured Data --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "WebApplication",
        "name": "{{ config('app.name', 'Brio') }}",
        "url": "{{ config('app.url') }}",
        "description": "Marketplace multi-services pour réservation de professionnels vérifiés en Belgique",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web, iOS, Android",
        "offers": {
            "@@type": "AggregateOffer",
            "priceCurrency": "EUR",
            "lowPrice": "25",
            "highPrice": "500"
        },
        "areaServed": {
            "@@type": "Country",
            "name": "Belgium"
        },
        "availableLanguage": ["French", "Dutch", "English"]
    }
    </script>

    {{-- Preload above-the-fold OG image for LCP --}}
    <link rel="preload" as="image" href="/images/og-brio.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Tout le design system (cx-* vitrine + cu-* outil) vit dans app.css --}}
    {{-- React Fast Refresh runtime (dev only; emits nothing in production builds).
         Required for the React Three Fiber island on the home hero. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Premium scroll engine (Lenis + GSAP ScrollTrigger). Chargé partout mais
         inerte tant qu'une page n'opte pas via [data-premium-scroll]/[data-scroll-*].
         Libs lourdes importées dynamiquement -> coût ~0 sur les pages non-premium. --}}
    @vite(['resources/css/premium-scroll.css', 'resources/js/premium-scroll.js'])
    {{-- Cinematic text reveal : CSS des masques + typo fluide. Inerte tant qu'un
         îlot React n'appelle pas useTextReveal (les styles s'arment via .ctr-split). --}}
    @vite(['resources/css/text-reveal.css'])
    {{-- Image reveal : effets clip/mask/blur/scale/parallax au scroll
         (IntersectionObserver). Inerte tant qu'aucun [data-image-reveal]. --}}
    @vite(['resources/css/image-reveal.css', 'resources/js/image-reveal.js'])
    {{-- Premium cursor : follower + boutons magnétiques + survol + aperçu image
         (un seul rAF, lerp). Inerte si pointeur grossier / reduced-motion. --}}
    @vite(['resources/css/premium-cursor.css', 'resources/js/premium-cursor.js'])
    {{-- Stats section animée (Framer Motion) : CSS global (drop-in). L'îlot
         React s'auto-monte sur [data-stats-section] et se charge PAR PAGE. --}}
    @vite(['resources/css/stats-section.css'])
    {{-- Floating nav : transparent→solide+blur, hide/reveal au scroll, menu
         mobile accessible. Arme [data-floating-nav] (le header guest). --}}
    @vite(['resources/css/floating-nav.css', 'resources/js/floating-nav.js'])
    @livewireStyles

    {{-- PostHog analytics — only loaded when POSTHOG_API_KEY is set (GDPR: loaded on user consent via cookie banner) --}}
    @if(config('analytics.posthog.api_key'))
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.src=s.api_host+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('{{ config("analytics.posthog.api_key") }}', {
            api_host: '{{ config("analytics.posthog.host", "https://eu.posthog.com") }}',
            person_profiles: 'identified_only',
        });
    </script>
    @endif
</head>

<body class="font-sans antialiased cx-shell">

    {{-- Atmosphère vitrine (classes définies dans app.css) --}}
    <div class="cx-starfield" aria-hidden="true"></div>

    {{-- Barre de progression du voyage --}}
    <div class="cx-progress" aria-hidden="true">
        <div class="cx-progress__bar" id="cxProgressBar"></div>
    </div>

    {{-- Lien d'évitement : keyboard a11y --}}
    <a href="#main-content" class="skip-to-content">Aller au contenu principal</a>

    <header class="cx-header" id="cxHeader" data-floating-nav data-fn-solid-at="24" data-fn-hide-after="140">
        <div class="cxnav__bar mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                {{-- Le site public est noir en permanence : la variante sombre, forcée. --}}
                <x-brand.logo space="client" variant="dark" :size="40" />
                <span class="leading-tight">
                    <span class="block text-lg font-extrabold tracking-tight" style="font-family:var(--cx-display);color:var(--cx-text)">
                        {{ config('app.name', 'Brio') }}
                    </span>
                    <span class="hidden text-[11px] uppercase tracking-[0.28em] sm:block" style="color:var(--cx-muted)">
                        Services à domicile
                    </span>
                </span>
            </a>

            <nav aria-label="Navigation principale" class="hidden items-center gap-7 text-sm md:flex">
                <a href="{{ route('home') }}#metiers" class="cx-nav-link">Métiers</a>
                <a href="{{ route('home') }}#fonctionnement" class="cx-nav-link">Fonctionnement</a>
                <a href="{{ route('home') }}#confiance" class="cx-nav-link">Confiance</a>
                <a href="{{ route('home') }}#b2b" class="cx-nav-link">Entreprises</a>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="cx-btn cx-btn--ghost px-4 py-2 text-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="cx-btn cx-btn--ghost hidden px-4 py-2 text-sm sm:inline-flex">Connexion</a>
                    <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary px-4 py-2 text-sm">Réserver</a>
                @endauth

                {{-- Bouton du menu mobile (caché en ≥ md, cf. floating-nav.css) --}}
                <button type="button" class="cxnav__toggle"
                        data-fn-toggle aria-expanded="false" aria-controls="cxnav-panel"
                        aria-label="Ouvrir le menu"
                        data-fn-label-open="Ouvrir le menu" data-fn-label-close="Fermer le menu">
                    <span class="cxnav__bars" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>
            </div>
        </div>
    </header>

    {{-- Menu mobile : disclosure modale accessible, piloté par floating-nav.
         inert au repos (hors tab + masqué AT) ; focus piégé à l'ouverture. --}}
    <div id="cxnav-panel" class="cxnav-panel" data-fn-panel role="dialog" aria-modal="true"
         aria-label="Menu de navigation" tabindex="-1" inert>
        <div class="cxnav-panel__backdrop" data-fn-close></div>
        <nav class="cxnav-panel__inner" aria-label="Menu mobile">
            <button type="button" class="cxnav-panel__close" data-fn-close aria-label="Fermer le menu">&times;</button>
            <a href="{{ route('home') }}#metiers" class="cxnav-panel__link">Métiers</a>
            <a href="{{ route('home') }}#fonctionnement" class="cxnav-panel__link">Fonctionnement</a>
            <a href="{{ route('home') }}#confiance" class="cxnav-panel__link">Confiance</a>
            <a href="{{ route('home') }}#b2b" class="cxnav-panel__link">Entreprises</a>
            <div class="cxnav-panel__cta">
                @auth
                    <a href="{{ route('dashboard') }}" class="cx-btn cx-btn--ghost px-4 py-3 text-sm">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="cx-btn cx-btn--ghost px-4 py-3 text-sm">Connexion</a>
                    <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary px-4 py-3 text-sm">Réserver</a>
                @endauth
            </div>
        </nav>
    </div>

    <main id="main-content" role="main" aria-label="Contenu principal">{{ $slot }}</main>

    {{-- CTA flottant permanent --}}
    @guest
    <a href="{{ route('booking.create') }}" class="cx-fab" aria-label="Réserver une prestation">
        <span class="cx-fab__dot"></span> Réserver maintenant
    </a>
    @endguest

    <footer class="cx-footer" role="contentinfo">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-10 md:grid-cols-4">
                <div>
                    <div class="flex items-center gap-3">
                        <x-brand.logo space="client" variant="dark" :size="36" />
                        <span class="text-lg font-extrabold" style="font-family:var(--cx-display)">{{ config('app.name', 'Brio') }}</span>
                    </div>
                    <p class="mt-4 max-w-xs text-sm" style="color:var(--cx-muted)">
                        La plateforme de services à domicile : du devis à la preuve d'exécution, en toute confiance.
                    </p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">Métiers</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="{{ route('home') }}#metiers">Nettoyage</a></li>
                        <li><a href="{{ route('home') }}#metiers">Peinture</a></li>
                        <li><a href="{{ route('home') }}#metiers">Bâtiment</a></li>
                        <li><a href="{{ route('home') }}#metiers">Jardinage</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">Plateforme</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="{{ route('booking.create') }}">Réserver</a></li>
                        @if(Route::has('premium.offer'))<li><a href="{{ route('premium.offer') }}">Premium</a></li>@endif
                        {{-- Ajoutés le 2026-08-05 : ces deux pages publiques n'étaient citées
                             nulle part sur le site. Le blog n'avait aucune porte d'entrée, et la
                             liste des services n'était atteignable que depuis l'accueil. --}}
                        @if (Route::has('services.index'))
                            <li><a href="{{ route('services.index') }}">Nos services</a></li>
                        @endif
                        @if (Route::has('blog.index'))
                            <li><a href="{{ route('blog.index') }}">Blog</a></li>
                        @endif
                        <li><a href="{{ route('home') }}#b2b">Entreprises</a></li>
                        <li><a href="{{ route('login') }}">Connexion</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">Légal</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="{{ route('terms.show') }}">Conditions générales</a></li>
                        <li><a href="{{ route('policy.show') }}">Confidentialité</a></li>
                        @if (Route::has('legal.cookies'))
                            <li><a href="{{ route('legal.cookies') }}">Cookies</a></li>
                        @endif
                        @if (Route::has('legal.mentions'))
                            <li><a href="{{ route('legal.mentions') }}">Mentions légales</a></li>
                        @endif
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:var(--cx-amber)">Ressources</p>
                    <ul class="mt-4 space-y-2 text-sm">
                        @if (Route::has('help.center'))
                            <li><a href="{{ route('help.center') }}">Aide / FAQ</a></li>
                        @endif
                        @if (Route::has('providers.browse.public'))
                            <li><a href="{{ route('providers.browse.public') }}">Trouver un prestataire</a></li>
                        @endif
                        @if (Route::has('booking.create'))
                            <li><a href="{{ route('booking.create') }}">Réserver une mission</a></li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="mt-12 flex flex-col items-start justify-between gap-3 border-t pt-6 sm:flex-row sm:items-center"
                 style="border-color:var(--cx-line)">
                <p class="text-xs" style="color:var(--cx-muted)">© {{ date('Y') }} {{ config('app.name', 'Brio') }}. Tous droits réservés.</p>
                <p class="text-xs" style="color:var(--cx-muted)">Conçu pour la Belgique &amp; l'Europe.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')

    <script>
        /* Barre de progression du voyage. Le header (fond/blur/hide-reveal) est
           géré par floating-nav. Scroll natif. */
        (function () {
            var bar = document.getElementById('cxProgressBar');
            if (!bar) return;
            var ticking = false;
            function update() {
                var h = document.documentElement;
                var max = (h.scrollHeight - h.clientHeight) || 1;
                var pct = Math.min(100, Math.max(0, (h.scrollTop || window.scrollY) / max * 100));
                bar.style.width = pct + '%';
                ticking = false;
            }
            window.addEventListener('scroll', function () {
                if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
            }, { passive: true });
            update();
        })();
    </script>
</body>

</html>