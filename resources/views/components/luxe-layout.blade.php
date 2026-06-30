<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">{{-- étude de design, dev only --}}
    <meta name="theme-color" content="#0c0d11">
    <title>{{ $title ?? 'Obsidia — A Global House of Ventures' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=fraunces:300,300i,400,400i,500,600|plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">

    {{-- React Fast Refresh runtime (dev only) — requis par l'îlot R3F du hero. --}}
    @viteReactRefresh
    {{-- Système éditorial (thème sombre via .ed--dark) + moteurs réutilisés.
         hero-r3f.jsx monte le fond WebGL R3F dans [data-lux-hero] [data-lux-r3f]. --}}
    @vite([
        'resources/css/premium-scroll.css',
        'resources/css/text-reveal.css',
        'resources/css/premium-cursor.css',
        'resources/css/floating-nav.css',
        'resources/css/editorial.css',
        'resources/js/premium-scroll.js',
        'resources/js/premium-cursor.js',
        'resources/js/floating-nav.js',
        'resources/js/editorial.js',
        'resources/js/hero-r3f.jsx',
    ])
</head>

<body class="ed ed--dark" id="top" data-premium-scroll>

    <div class="ed-grain" aria-hidden="true"></div>
    <div class="ed-progress" data-scroll-progress aria-hidden="true"></div>

    <a href="#main" class="ed-eyebrow" style="position:absolute;left:-9999px;top:0;background:var(--ed-ink);color:var(--ed-paper);padding:.6rem 1rem;z-index:200"
       onfocus="this.style.left='0'" onblur="this.style.left='-9999px'">Skip to content</a>

    {{-- Nav : moteur floating-nav réutilisé (re-thémé sombre via .ed--dark). --}}
    <header class="ed-header" data-floating-nav data-fn-solid-at="20" data-fn-hide-after="160">
        <div class="cxnav__bar ed-nav__bar">
            <a href="#top" class="ed-wordmark">Obsidia<sup>®</sup></a>
            <nav class="ed-nav__links" aria-label="Primary">
                <a href="#group" class="ed-navlink">Group</a>
                <a href="#ventures" class="ed-navlink">Ventures</a>
                <a href="#services" class="ed-navlink">Services</a>
                <a href="#presence" class="ed-navlink">Presence</a>
                <a href="#contact" class="ed-navlink">Contact</a>
            </nav>
            <div style="display:flex;align-items:center;gap:.6rem">
                <a href="#contact" class="ed-cta" data-magnetic data-magnetic-strength="0.3" data-cursor-text="Hello">
                    <span data-magnetic-inner>Connect</span>
                    <span class="ed-cta__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </span>
                </a>
                <button type="button" class="cxnav__toggle" data-fn-toggle aria-expanded="false"
                        aria-controls="cxnav-panel" aria-label="Open menu"
                        data-fn-label-open="Open menu" data-fn-label-close="Close menu">
                    <span class="cxnav__bars" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>
            </div>
        </div>
    </header>

    <div id="cxnav-panel" class="cxnav-panel" data-fn-panel role="dialog" aria-modal="true"
         aria-label="Menu" tabindex="-1" inert>
        <div class="cxnav-panel__backdrop" data-fn-close></div>
        <nav class="cxnav-panel__inner" aria-label="Mobile">
            <button type="button" class="cxnav-panel__close" data-fn-close aria-label="Close menu">&times;</button>
            <a href="#group" class="cxnav-panel__link">Group</a>
            <a href="#ventures" class="cxnav-panel__link">Ventures</a>
            <a href="#services" class="cxnav-panel__link">Services</a>
            <a href="#presence" class="cxnav-panel__link">Presence</a>
            <a href="#contact" class="cxnav-panel__link">Contact</a>
            <div class="cxnav-panel__cta">
                <a href="#contact" class="ed-cta" style="justify-content:space-between">
                    <span>Connect</span>
                    <span class="ed-cta__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </span>
                </a>
            </div>
        </nav>
    </div>

    <main id="main">{{ $slot }}</main>

    <footer class="ed-footer">
        <div class="ed-container ed-section--tight">
            <p class="ed-footer__big" data-ed-reveal style="max-width:13ch">In permanent <span class="ed-italic ed-accent">motion</span>.</p>
            <div class="ed-grid" style="margin-top:var(--s-2xl);row-gap:var(--s-2xl)">
                <div class="ed-col-5" data-ed-reveal>
                    <a href="#top" class="ed-wordmark" style="font-size:2rem">Obsidia<sup>®</sup></a>
                    <p class="ed-body" style="margin-top:var(--s-md)">A global house of ventures — capital, craft, and
                        conviction, compounding across decades.</p>
                </div>
                <nav class="ed-col-3 ed-start-7" aria-label="Footer — group" data-ed-reveal style="--i:1">
                    <p class="ed-eyebrow" style="margin-bottom:var(--s-sm)">Group</p>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
                        <li><a class="ed-navlink" href="#group">Overview</a></li>
                        <li><a class="ed-navlink" href="#ventures">Ventures</a></li>
                        <li><a class="ed-navlink" href="#services">Services</a></li>
                        <li><a class="ed-navlink" href="#presence">Presence</a></li>
                    </ul>
                </nav>
                <nav class="ed-col-3" aria-label="Footer — elsewhere" data-ed-reveal style="--i:2">
                    <p class="ed-eyebrow" style="margin-bottom:var(--s-sm)">Elsewhere</p>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
                        <li><a class="ed-navlink" href="#contact">Press</a></li>
                        <li><a class="ed-navlink" href="#contact">Careers</a></li>
                        <li><a class="ed-navlink" href="#contact">LinkedIn</a></li>
                    </ul>
                </nav>
            </div>
            <div style="margin-top:var(--s-2xl);padding-top:var(--s-md);border-top:1px solid var(--ed-line);display:flex;flex-wrap:wrap;gap:var(--s-sm);justify-content:space-between">
                <p class="ed-caption">© {{ date('Y') }} Obsidia — a fictional brand, original content.</p>
                <p class="ed-caption">Built with intent · {{ strtoupper(app()->getLocale()) }}</p>
            </div>
        </div>
    </footer>

</body>

</html>
