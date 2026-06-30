<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">{{-- étude de design, dev only --}}
    <title>{{ $title ?? 'Northlight — Design & Innovation Studio' }}</title>

    {{-- Typo premium (NON bannie) : Fraunces (serif éditorial à fort contraste,
         optique) + Plus Jakarta Sans (grotesque humaniste). Via Bunny Fonts. --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=fraunces:300,300i,400,400i,500,600|plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet">

    {{-- Système éditorial + moteurs réutilisés (re-thémés via le pont --cx-* dans .ed).
         editorial.css est chargé en dernier → ses overrides priment. --}}
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
    ])
</head>

<body class="ed" id="top" data-premium-scroll>

    {{-- Grain film (overlay fixe, pointer-events none) --}}
    <div class="ed-grain" aria-hidden="true"></div>

    {{-- Ligne de progression de lecture (moteur premium-scroll → --scroll-progress) --}}
    <div class="ed-progress" data-scroll-progress aria-hidden="true"></div>

    <a href="#main" class="ed-eyebrow" style="position:absolute;left:-9999px;top:0;background:var(--ed-ink);color:var(--ed-paper);padding:.6rem 1rem;border-radius:0 0 .5rem 0;z-index:200"
       onfocus="this.style.left='0'" onblur="this.style.left='-9999px'">Skip to content</a>

    {{-- ── Nav : moteur floating-nav réutilisé (pill flottant, hide/reveal, menu a11y) ── --}}
    <header class="ed-header" data-floating-nav data-fn-solid-at="20" data-fn-hide-after="160">
        <div class="cxnav__bar ed-nav__bar">
            <a href="#top" class="ed-wordmark">Northlight<sup>®</sup></a>

            <nav class="ed-nav__links" aria-label="Primary">
                <a href="#studio" class="ed-navlink">Studio</a>
                <a href="#work" class="ed-navlink">Work</a>
                <a href="#thinking" class="ed-navlink">Thinking</a>
                <a href="#contact" class="ed-navlink">Contact</a>
            </nav>

            <div style="display:flex;align-items:center;gap:.6rem">
                <a href="#contact" class="ed-cta" data-magnetic data-magnetic-strength="0.3" data-cursor-text="Hello">
                    <span data-magnetic-inner>Start a project</span>
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

    {{-- Menu mobile (disclosure modale accessible — moteur floating-nav) --}}
    <div id="cxnav-panel" class="cxnav-panel" data-fn-panel role="dialog" aria-modal="true"
         aria-label="Menu" tabindex="-1" inert>
        <div class="cxnav-panel__backdrop" data-fn-close></div>
        <nav class="cxnav-panel__inner" aria-label="Mobile">
            <button type="button" class="cxnav-panel__close" data-fn-close aria-label="Close menu">&times;</button>
            <a href="#studio" class="cxnav-panel__link">Studio</a>
            <a href="#work" class="cxnav-panel__link">Work</a>
            <a href="#thinking" class="cxnav-panel__link">Thinking</a>
            <a href="#contact" class="cxnav-panel__link">Contact</a>
            <div class="cxnav-panel__cta">
                <a href="#contact" class="ed-cta" style="justify-content:space-between">
                    <span>Start a project</span>
                    <span class="ed-cta__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </span>
                </a>
            </div>
        </nav>
    </div>

    <main id="main">{{ $slot }}</main>

    {{-- ── Footer éditorial ── --}}
    <footer class="ed-footer">
        <div class="ed-container ed-section--tight">
            <div class="ed-grid" style="row-gap:var(--s-2xl)">
                <div class="ed-col-6" data-ed-reveal>
                    <a href="#top" class="ed-wordmark" style="font-size:2rem">Northlight<sup>®</sup></a>
                    <p class="ed-body" style="margin-top:var(--s-md)">An independent design &amp; innovation studio shaping
                        brands, products, and the spaces between them — from first principle to last pixel.</p>
                </div>
                <nav class="ed-col-3 ed-start-7" aria-label="Footer — studio" data-ed-reveal style="--i:1">
                    <p class="ed-eyebrow" style="margin-bottom:var(--s-sm)">Studio</p>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
                        <li><a class="ed-navlink" href="#studio">About</a></li>
                        <li><a class="ed-navlink" href="#work">Work</a></li>
                        <li><a class="ed-navlink" href="#thinking">Thinking</a></li>
                        <li><a class="ed-navlink" href="#contact">Contact</a></li>
                    </ul>
                </nav>
                <nav class="ed-col-3" aria-label="Footer — elsewhere" data-ed-reveal style="--i:2">
                    <p class="ed-eyebrow" style="margin-bottom:var(--s-sm)">Elsewhere</p>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
                        <li><a class="ed-navlink" href="#contact">Newsletter</a></li>
                        <li><a class="ed-navlink" href="#contact">Instagram</a></li>
                        <li><a class="ed-navlink" href="#contact">LinkedIn</a></li>
                    </ul>
                </nav>
            </div>
            <div style="margin-top:var(--s-2xl);padding-top:var(--s-md);border-top:1px solid var(--ed-line);display:flex;flex-wrap:wrap;gap:var(--s-sm);justify-content:space-between">
                <p class="ed-caption">© {{ date('Y') }} Northlight Studio — a fictional brand, original content.</p>
                <p class="ed-caption">Crafted with intent · {{ strtoupper(app()->getLocale()) }}</p>
            </div>
        </div>
    </footer>

</body>

</html>
