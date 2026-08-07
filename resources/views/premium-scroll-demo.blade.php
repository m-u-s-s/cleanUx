{{--
    Premium Scroll Engine — vitrine interactive (dev/staging only, cf. routes/public.php).
    Démontre les 6 features pilotées par data-attributes :
      smooth scroll · parallaxe · épinglage · progression · horizontal · responsive
    Le moteur (resources/js/premium-scroll.js) est chargé globalement par le layout
    guest et s'active ici grâce à [data-premium-scroll] sur le wrapper.
--}}
<x-guest-layout>

    {{-- data-premium-scroll : active le smooth scroll Lenis pour cette page --}}
    <div data-premium-scroll class="psd">

        {{-- ───────────────────────── 1 · PARALLAXE (hero multi-couches) ───────────────────────── --}}
        <header class="psd-hero" data-scroll-clip>
            <div class="psd-hero__layer psd-hero__bg" data-scroll-parallax="0.5" aria-hidden="true"></div>
            <div class="psd-hero__layer psd-hero__mid" data-scroll-parallax="0.26" aria-hidden="true"></div>

            <div class="psd-hero__content" data-cx-reveal>
                <p class="psd-eyebrow">Premium scroll engine</p>
                <h1 class="psd-title">Le scroll<br>comme une expérience</h1>
                <p class="psd-lead">
                    Lenis pour la fluidité, GSAP ScrollTrigger pour la narration.
                    Épinglage, parallaxe, sections horizontales et progression — sans
                    le moindre layout shift, et 100&nbsp;% accessible (reduced-motion respecté).
                </p>
                <span class="psd-hint" aria-hidden="true">Défilez pour explorer ↓</span>
            </div>

            <div class="psd-hero__layer psd-hero__glow" data-scroll-parallax="-0.18" aria-hidden="true"></div>
        </header>

        {{-- ───────────────────────── 2 · ÉPINGLAGE (pin) ───────────────────────── --}}
        <section class="psd-section">
            <div class="psd-pin" data-scroll-pin data-scroll-pin-end="+=120%">
                <div class="psd-pin__card" data-cx-reveal>
                    <span class="psd-num">01</span>
                    <h2>Section épinglée</h2>
                    <p>
                        Cette carte reste fixée à l'écran pendant que vous continuez à
                        défiler. <code>pinSpacing</code> réserve automatiquement l'espace,
                        donc <strong>aucun saut de mise en page</strong> au verrouillage
                        ou à la libération.
                    </p>
                    <p class="psd-tag"><code>data-scroll-pin</code></p>
                </div>
            </div>
        </section>

        {{-- ───────────────────────── 3 · PROGRESSION (progress) ───────────────────────── --}}
        <section id="psd-progress-scope" class="psd-section psd-section--tall">
            <div class="psd-progress-wrap" data-cx-reveal>
                <span class="psd-num">02</span>
                <h2>Progression au scroll</h2>
                <p>La barre se remplit selon votre avancée dans cette section (scope local).</p>

                {{-- Progress scopé à cette section (#psd-progress-scope) --}}
                <div class="cx-scroll-progress psd-bar"
                     data-scroll-progress="#psd-progress-scope"
                     role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
                     aria-label="Progression dans la section"></div>

                <p class="psd-tag"><code>data-scroll-progress="#psd-progress-scope"</code></p>
                <p class="psd-muted">Variante page entière : <code>data-scroll-progress</code> sans scope.</p>
            </div>
        </section>

        {{-- ───────────────────────── 4 · HORIZONTAL ───────────────────────── --}}
        <section class="psd-horizontal" data-scroll-horizontal aria-label="Galerie à défilement horizontal">
            <div class="psd-track" data-scroll-track>
                {{-- [data-scroll-panel-inner] = la cible des animations scale + translateX + fade --}}
                <article class="psd-panel" data-scroll-panel>
                    <div class="psd-panel__inner" data-scroll-panel-inner>
                        <span class="psd-num">03</span>
                        <h2>Sections horizontales</h2>
                        <p>Le défilement vertical devient horizontal. La section est épinglée, le rail glisse, et chaque panneau fait un <strong>scale + translateX + fade</strong> à son passage.</p>
                        <span class="psd-hint" aria-hidden="true">→</span>
                    </div>
                </article>
                <article class="psd-panel psd-panel--a" data-scroll-panel>
                    <div class="psd-panel__inner" data-scroll-panel-inner>
                        <h3>Dispatch</h3>
                        <p>Un pro vérifié, en 2 minutes.</p>
                    </div>
                </article>
                <article class="psd-panel psd-panel--b" data-scroll-panel>
                    <div class="psd-panel__inner" data-scroll-panel-inner>
                        <h3>Suivi temps réel</h3>
                        <p>QR départ, QR fin, paiement sécurisé.</p>
                    </div>
                </article>
                <article class="psd-panel psd-panel--c" data-scroll-panel>
                    <div class="psd-panel__inner" data-scroll-panel-inner>
                        <h3>Preuve d'exécution</h3>
                        <p>Photos avant/après, satisfaction garantie.</p>
                        <span class="psd-tag"><code>data-scroll-horizontal</code></span>
                    </div>
                </article>
            </div>
        </section>

        {{-- ───────────────────────── 4bis · CINEMATIC TEXT REVEAL (hooks React) ───────────────────────── --}}
        <section class="psd-section psd-section--tall">
            <div class="psd-reveal-wrap">
                <span class="psd-num">03·5</span>
                {{-- Îlot React : useTextReveal / RevealText (line split + stagger +
                     clip-path + opacity + responsive). Monté par text-reveal-demo.jsx. --}}
                <div id="text-reveal-demo"></div>
                <p class="psd-tag"><code>useTextReveal()</code> · <code>&lt;RevealText/&gt;</code></p>
            </div>
        </section>

        {{-- ───────────────────────── 4ter · IMAGE REVEAL (IntersectionObserver) ───────────────────────── --}}
        <section class="psd-section psd-section--tall">
            <div class="psd-images-wrap">
                <span class="psd-num">04·7</span>
                <h2>Révélation d'images</h2>
                <p>clip-path · masque · blur→net · scale · parallaxe — déclenchés à l'entrée dans le viewport (IntersectionObserver).</p>

                <div class="psd-img-grid">
                    <div class="psd-img">
                        <figure class="psd-img__frame" data-image-reveal data-ir-effect="clip fade"><img src="/images/og-brio.svg" alt="Démonstration clip-path"></figure>
                        <p class="psd-img__cap"><code>clip</code></p>
                    </div>
                    <div class="psd-img">
                        <figure class="psd-img__frame" data-image-reveal data-ir-effect="mask fade"><img src="/images/og-brio.svg" alt="Démonstration masque"></figure>
                        <p class="psd-img__cap"><code>mask</code></p>
                    </div>
                    <div class="psd-img">
                        <figure class="psd-img__frame" data-image-reveal data-ir-effect="blur fade"><img src="/images/og-brio.svg" alt="Démonstration blur"></figure>
                        <p class="psd-img__cap"><code>blur</code> → net</p>
                    </div>
                    <div class="psd-img">
                        <figure class="psd-img__frame" data-image-reveal data-ir-effect="scale fade"><img src="/images/og-brio.svg" alt="Démonstration scale"></figure>
                        <p class="psd-img__cap"><code>scale</code></p>
                    </div>
                </div>

                {{-- Combo + parallaxe (le cadre flotte au scroll, gaté par la visibilité) --}}
                <div class="psd-img psd-img--wide">
                    <figure class="psd-img__frame" data-image-reveal data-ir-effect="clip blur fade" data-ir-parallax="0.25"><img src="/images/og-brio.svg" alt="Démonstration clip + blur + parallaxe"></figure>
                    <p class="psd-img__cap"><code>clip + blur + parallax</code></p>
                </div>

                <p class="psd-tag"><code>&lt;figure data-image-reveal data-ir-effect="clip blur" data-ir-parallax="0.2"&gt;</code> · React : <code>&lt;RevealImage/&gt;</code></p>
            </div>
        </section>

        {{-- ───────────────────────── 4·∞ · CURSEUR PREMIUM ───────────────────────── --}}
        <section class="psd-section">
            <div class="psd-section__inner" data-cx-reveal>
                <span class="psd-num">04·∞</span>
                <h2>Curseur premium</h2>
                <p>Follower deux couches (point + anneau), boutons magnétiques, états de survol et aperçu image — un seul <code>requestAnimationFrame</code>, interpolation douce (lerp). Inerte au toucher / en <code>prefers-reduced-motion</code>.</p>

                {{-- Boutons magnétiques + label de survol --}}
                <div class="psd-cursor-row">
                    <button class="cx-btn cx-btn--primary psd-magnet" data-magnetic data-magnetic-strength="0.4">
                        <span data-magnetic-inner>Aimanté</span>
                    </button>
                    <button class="cx-btn cx-btn--ghost psd-magnet" data-magnetic data-magnetic-strength="0.25" data-cursor-text="Clique">
                        <span data-magnetic-inner>Aimant doux + label</span>
                    </button>
                    <a href="#" class="psd-cursor-link" data-cursor-text="Voir">Lien avec label « Voir »</a>
                </div>

                {{-- Aperçu image au survol --}}
                <ul class="psd-cursor-list">
                    <li><a href="#" data-cursor-image="/images/og-brio.svg">Nettoyage premium <span>→</span></a></li>
                    <li><a href="#" data-cursor-image="/images/og-brio.svg">Peinture &amp; finitions <span>→</span></a></li>
                    <li><a href="#" data-cursor-image="/images/og-brio.svg">Jardinage &amp; extérieurs <span>→</span></a></li>
                </ul>

                <p class="psd-tag"><code>&lt;button data-magnetic data-magnetic-strength="0.4"&gt;</code> · <code>data-cursor-text="Voir"</code> · <code>data-cursor-image="/x.jpg"</code> · React : <code>&lt;MagneticButton/&gt;</code></p>
            </div>
        </section>

        {{-- ───────────────────────── 4·Σ · STATISTIQUES ANIMÉES (Framer Motion) ───────────────────────── --}}
        <section class="psd-section">
            <div class="psd-stats-wrap" data-cx-reveal>
                <span class="psd-num">04·Σ</span>
                <h2>Statistiques animées</h2>
                <p>Count-up, progress bars, cartes responsive et révélation en cascade — déclenchés à l'entrée dans le viewport (Framer Motion : <code>useInView</code> + variants <code>staggerChildren</code> + <code>useMotionValue</code>).</p>

                {{-- Îlot React auto-monté par resources/js/stats-section.jsx --}}
                <div data-stats-section>
                    <script type="application/json">
                    {
                        "eyebrow": "Brio en chiffres",
                        "title": "La confiance, en données",
                        "stats": [
                            { "value": 12500, "suffix": "+", "label": "Missions réalisées", "progress": 92, "icon": "🧹" },
                            { "value": 4.9, "decimals": 1, "suffix": "/5", "label": "Note moyenne", "progress": 98, "icon": "⭐" },
                            { "value": 32, "suffix": "+", "label": "Métiers couverts", "progress": 74, "icon": "🛠️" },
                            { "value": 98, "suffix": "%", "label": "Clients satisfaits", "progress": 98, "icon": "💬" }
                        ]
                    }
                    </script>
                </div>

                <p class="psd-tag"><code>&lt;div data-stats-section&gt;&lt;script type="application/json"&gt;{…}&lt;/script&gt;&lt;/div&gt;</code> · React : <code>&lt;StatsSection/&gt;</code></p>
            </div>
        </section>

        {{-- ───────────────────────── 5 · PARALLAXE de fin + responsive ───────────────────────── --}}
        <section class="psd-section psd-section--tall psd-outro" data-scroll-clip>
            <div class="psd-outro__blob" data-scroll-parallax="0.4" aria-hidden="true"></div>
            <div class="psd-outro__content" data-cx-reveal>
                <span class="psd-num">04</span>
                <h2>Fluide partout</h2>
                <p>
                    Responsive natif : parallaxe adoucie et amplitudes recalculées au
                    resize via <code>gsap.matchMedia()</code>. Sur tactile, le momentum
                    natif est conservé. En <code>prefers-reduced-motion</code>, tout est
                    statique et lisible.
                </p>

                <details class="psd-usage">
                    <summary>Comment l'utiliser sur n'importe quelle page</summary>
<pre><code>&lt;div data-premium-scroll&gt;            {{-- smooth scroll Lenis --}}

  &lt;div data-scroll-clip&gt;
    &lt;div data-scroll-parallax="0.4"&gt;&lt;/div&gt;   {{-- parallaxe --}}
  &lt;/div&gt;

  &lt;section data-scroll-pin
           data-scroll-pin-end="+=100%"&gt;...&lt;/section&gt;   {{-- épinglage --}}

  &lt;div class="cx-scroll-progress"
       data-scroll-progress&gt;&lt;/div&gt;          {{-- progression page --}}

  &lt;section data-scroll-horizontal&gt;          {{-- horizontal (pin desktop, --}}
    &lt;div data-scroll-track&gt;                  {{--  scroll natif tactile mobile) --}}
      &lt;article data-scroll-panel&gt;
        &lt;div data-scroll-panel-inner&gt;...&lt;/div&gt;  {{-- scale+translateX+fade --}}
      &lt;/article&gt;
    &lt;/div&gt;
  &lt;/section&gt;
&lt;/div&gt;</code></pre>
                </details>

                <a href="{{ route('home') }}" class="cx-btn cx-btn--primary psd-cta">Retour à l'accueil</a>
            </div>
        </section>
    </div>

    <style>
        /* Démo uniquement (dev/staging) — namespacé .psd-, sur le shell sombre cx-*. */
        .psd { position: relative; }
        .psd h2 { font-family: var(--cx-display); font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 800; color: var(--cx-text); line-height: 1.05; }
        .psd h3 { font-family: var(--cx-display); font-size: clamp(1.5rem, 3.5vw, 2.4rem); font-weight: 800; color: var(--cx-text); }
        .psd p { color: var(--cx-muted); max-width: 46ch; line-height: 1.6; margin-top: 1rem; }
        .psd code { font-family: var(--cx-mono, ui-monospace, monospace); font-size: 0.85em; color: var(--cx-amber); background: color-mix(in srgb, var(--cx-amber) 12%, transparent); padding: 0.15em 0.45em; border-radius: 6px; }
        .psd .psd-num { display: inline-block; font-family: var(--cx-display); font-weight: 800; font-size: 0.95rem; letter-spacing: 0.3em; color: var(--cx-amber); margin-bottom: 0.75rem; }
        .psd .psd-tag { margin-top: 1.5rem; }
        .psd .psd-muted { font-size: 0.85rem; opacity: 0.7; }
        .psd .psd-hint { display: inline-block; margin-top: 2rem; font-size: 0.8rem; letter-spacing: 0.28em; text-transform: uppercase; color: var(--cx-muted); }

        /* 1 · Hero parallaxe */
        .psd-hero { position: relative; min-height: 100vh; display: grid; place-items: center; padding: 6rem 1.5rem; text-align: center; isolation: isolate; }
        .psd-hero__layer { position: absolute; inset: -20% 0; z-index: -1; pointer-events: none; }
        .psd-hero__bg { background: radial-gradient(60% 50% at 50% 30%, color-mix(in srgb, var(--cx-amber) 22%, transparent), transparent 70%); }
        .psd-hero__mid { background: radial-gradient(40% 40% at 70% 60%, rgba(99,102,241,0.22), transparent 70%); }
        .psd-hero__glow { inset: 30% 0 -10%; background: radial-gradient(50% 40% at 30% 80%, rgba(147,51,234,0.18), transparent 70%); }
        .psd-hero__content { max-width: 56ch; }
        .psd-eyebrow { font-size: 0.8rem; letter-spacing: 0.32em; text-transform: uppercase; color: var(--cx-amber); margin: 0; }
        .psd-title { font-family: var(--cx-display); font-weight: 900; font-size: clamp(2.6rem, 8vw, 6rem); line-height: 0.98; color: var(--cx-text); margin: 1.25rem 0; letter-spacing: -0.02em; }
        .psd-lead { margin: 0 auto; }

        /* sections génériques */
        .psd-section { position: relative; padding: 8vh 1.5rem; display: grid; place-items: center; }
        .psd-section--tall { min-height: 140vh; }

        /* 2 · Pin */
        .psd-pin { min-height: 100vh; display: grid; place-items: center; width: 100%; }
        .psd-pin__card { max-width: 52ch; text-align: center; padding: 2.5rem; border: 1px solid var(--cx-line); border-radius: 22px; background: color-mix(in srgb, var(--cx-text) 4%, transparent); backdrop-filter: blur(8px); }
        .psd-pin__card p { margin-left: auto; margin-right: auto; }

        /* 3 · Progress */
        .psd-progress-wrap { width: min(640px, 92vw); text-align: center; }
        .psd-progress-wrap p { margin-left: auto; margin-right: auto; }
        .psd-bar { height: 10px; border-radius: 999px; margin: 2rem auto 0; background: color-mix(in srgb, var(--cx-amber) 14%, transparent); }

        /* 4 · Horizontal */
        .psd-panel { display: grid; align-content: center; padding: clamp(1.5rem, 6vw, 6rem); text-align: left; }
        .psd-panel__inner { display: grid; justify-items: start; gap: 0.25rem; }
        .psd-panel h2, .psd-panel p { max-width: 40ch; }
        .psd-panel--a { background: linear-gradient(135deg, rgba(99,102,241,0.16), transparent); }
        .psd-panel--b { background: linear-gradient(135deg, rgba(245,158,11,0.16), transparent); }
        .psd-panel--c { background: linear-gradient(135deg, rgba(147,51,234,0.16), transparent); }
        .psd-panel .psd-hint { font-size: 2rem; letter-spacing: 0; margin-top: 1.5rem; }

        /* 4bis · Cinematic text reveal (îlot React) */
        .psd-reveal-wrap { width: min(760px, 92vw); text-align: center; }
        .trd-eyebrow { font-size: 0.8rem; letter-spacing: 0.32em; text-transform: uppercase; color: var(--cx-amber); margin: 0 0 1.5rem; }
        .trd-h { color: var(--cx-text); margin: 0 auto; max-width: 18ch; }
        .trd-p { color: var(--cx-muted); margin: 1.5rem auto 2.5rem; max-width: 52ch; }
        .trd-img { margin: 2.5rem auto 0; max-width: 560px; border-radius: 16px; border: 1px solid var(--cx-line); }

        /* 4ter · Image reveal */
        .psd-images-wrap { width: min(960px, 92vw); text-align: center; }
        .psd-images-wrap > p { margin-left: auto; margin-right: auto; }
        .psd-img-grid { margin-top: 2.5rem; display: grid; gap: 1.25rem; grid-template-columns: repeat(2, 1fr); }
        @media (min-width: 768px) { .psd-img-grid { grid-template-columns: repeat(4, 1fr); } }
        .psd-img__frame { margin: 0; border-radius: 16px; aspect-ratio: 16 / 10; border: 1px solid var(--cx-line); background: #0b1220; }
        .psd-img__frame img { height: 100%; object-fit: cover; }
        .psd-img__cap { margin-top: 0.6rem; font-size: 0.8rem; color: var(--cx-muted); }
        .psd-img--wide { margin-top: 1.75rem; }
        .psd-img--wide .psd-img__frame { aspect-ratio: 21 / 9; }

        /* 4·∞ · Curseur premium */
        .psd-section__inner { width: min(640px, 92vw); text-align: center; }
        .psd-section__inner p { margin-left: auto; margin-right: auto; }
        .psd-cursor-row { margin-top: 2.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: center; }
        .psd-magnet { will-change: translate; }
        .psd-cursor-link { color: var(--cx-text); font-weight: 600; text-decoration: none; border-bottom: 1px solid var(--cx-amber); padding-bottom: 2px; }
        .psd-cursor-list { margin: 2.5rem auto 0; max-width: 420px; list-style: none; padding: 0; text-align: left; border-top: 1px solid var(--cx-line); }
        .psd-cursor-list li { border-bottom: 1px solid var(--cx-line); }
        .psd-cursor-list a { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0.25rem; color: var(--cx-text); font-family: var(--cx-display); font-size: 1.1rem; font-weight: 700; text-decoration: none; transition: padding 0.3s cubic-bezier(0.16, 1, 0.3, 1), color 0.3s ease; }
        .psd-cursor-list a span { color: var(--cx-amber); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .psd-cursor-list a:hover { padding-left: 1rem; color: var(--cx-amber); }
        .psd-cursor-list a:hover span { transform: translateX(6px); }

        /* 4·Σ · Stats animées */
        .psd-stats-wrap { width: min(1100px, 94vw); text-align: center; }
        .psd-stats-wrap > p { margin-left: auto; margin-right: auto; }
        .psd-stats-wrap .cx-stats { margin-top: 2.75rem; }

        /* 5 · Outro */
        .psd-outro { isolation: isolate; }
        .psd-outro__blob { position: absolute; inset: 10% -10% auto; height: 60vh; z-index: -1; background: radial-gradient(50% 50% at 50% 50%, rgba(99,102,241,0.2), transparent 70%); pointer-events: none; }
        .psd-outro__content { width: min(640px, 92vw); text-align: center; }
        .psd-outro__content p { margin-left: auto; margin-right: auto; }
        .psd-cta { margin-top: 2rem; }
        .psd-usage { margin: 2rem auto 0; max-width: 640px; text-align: left; border: 1px solid var(--cx-line); border-radius: 14px; padding: 1rem 1.25rem; }
        .psd-usage summary { cursor: pointer; color: var(--cx-text); font-weight: 600; }
        .psd-usage pre { margin-top: 1rem; overflow-x: auto; font-size: 0.78rem; line-height: 1.55; color: var(--cx-muted); }
        .psd-usage code { background: none; color: inherit; padding: 0; }

        @media (prefers-reduced-motion: reduce) {
            .psd-hint { display: none; }
        }
    </style>

    @push('scripts')
        {{-- Îlot React de démo des hooks de révélation de texte (dev only). --}}
        @vite('resources/js/text-reveal-demo.jsx')
        {{-- Îlot React de la section stats animée (Framer Motion). Auto-monté
             sur [data-stats-section]. Chargé par page (React + motion). --}}
        @vite('resources/js/stats-section.jsx')
    @endpush
</x-guest-layout>
