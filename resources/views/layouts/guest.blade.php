<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#070b14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CleanUx">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO multi-métiers --}}
    <title>{{ config('app.name', 'CleanUx') }} | Services à domicile : nettoyage, peinture, bâtiment, jardinage</title>
    <meta name="description" content="Réservez un service à domicile en quelques minutes : nettoyage, peinture, bâtiment, jardinage. Devis clair, suivi de l'intervenant en temps réel, validation par code et preuve photo.">
    <meta property="og:title" content="CleanUx — Marketplace de services à domicile">
    <meta property="og:description" content="Réservez un prestataire qualifié en 5 étapes. Nettoyage, peinture, babysitting et 20+ métiers.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:image" content="{{ asset('images/og-cleanux.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="robots" content="index,follow">

    {{-- Preload above-the-fold OG image for LCP --}}
    <link rel="preload" as="image" href="/images/og-cleanux.jpg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900|space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Tout le design system (cx-* vitrine + cu-* outil) vit dans app.css --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
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

    <header class="cx-header" id="cxHeader">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="cx-logo-mark">Cx</span>
                <span class="leading-tight">
                    <span class="block text-lg font-extrabold tracking-tight" style="font-family:var(--cx-display);color:var(--cx-text)">
                        {{ config('app.name', 'CleanUx') }}
                    </span>
                    <span class="hidden text-[11px] uppercase tracking-[0.28em] sm:block" style="color:var(--cx-muted)">
                        Services à domicile
                    </span>
                </span>
            </a>

            <nav class="hidden items-center gap-7 text-sm md:flex">
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
            </div>
        </div>
    </header>

    <main id="main-content">{{ $slot }}</main>

    {{-- CTA flottant permanent --}}
    @guest
    <a href="{{ route('booking.create') }}" class="cx-fab" aria-label="Réserver une prestation">
        <span class="cx-fab__dot"></span> Réserver maintenant
    </a>
    @endguest

    <footer class="cx-footer">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid gap-10 md:grid-cols-4">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="cx-logo-mark" style="height:36px;width:36px;font-size:14px">Cx</span>
                        <span class="text-lg font-extrabold" style="font-family:var(--cx-display)">{{ config('app.name', 'CleanUx') }}</span>
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
                <p class="text-xs" style="color:var(--cx-muted)">© {{ date('Y') }} {{ config('app.name', 'CleanUx') }}. Tous droits réservés.</p>
                <p class="text-xs" style="color:var(--cx-muted)">Conçu pour la Belgique &amp; l'Europe.</p>
            </div>
        </div>
    </footer>

    @livewireScripts
    @stack('scripts')

    <script>
        /* Progression du voyage + opacité du header au scroll. Scroll natif. */
        (function () {
            var bar = document.getElementById('cxProgressBar');
            var header = document.getElementById('cxHeader');
            var ticking = false;
            function update() {
                var h = document.documentElement;
                var max = (h.scrollHeight - h.clientHeight) || 1;
                var pct = Math.min(100, Math.max(0, (h.scrollTop || window.scrollY) / max * 100));
                if (bar) bar.style.width = pct + '%';
                if (header) header.style.background = (window.scrollY > 40)
                    ? 'rgba(7,11,20,0.78)' : 'rgba(7,11,20,0.55)';
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