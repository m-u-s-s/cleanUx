/* ============================================================================
   Brio — Premium Scroll Engine
   Lenis (smooth scroll) + GSAP ScrollTrigger (scroll-driven behaviors).
   ----------------------------------------------------------------------------
   GLOBAL OPT-IN : ce bundle est chargé sur TOUTES les pages guest mais reste
   100 % inerte tant que la page n'opte pas explicitement via un data-attribute.
   Les libs lourdes (gsap / ScrollTrigger / lenis) sont chargées en IMPORT
   DYNAMIQUE uniquement quand la page les utilise → coût ~0 sur les pages qui
   n'activent pas le moteur (le bootstrap synchrone fait juste un querySelector).

   ROBUSTESSE (mêmes conventions que home-journey.js / luxury-hero.js) :
   - prefers-reduced-motion  -> moteur désactivé, contenu 100 % visible, scroll natif.
   - pointeur grossier / tactile -> Lenis ne lisse PAS le tactile (momentum natif),
     parallaxe adoucie. Aucune capture du scroll natif.
   - Aucun layout shift : transformations (transform/opacity) uniquement, l'espace
     des sections épinglées est réservé (pinSpacing), styles inline restaurés au
     teardown. Rien n'est pré-masqué : si le JS échoue, tout reste lisible.
   - Livewire 3 : teardown sur `livewire:navigating`, re-init sur `livewire:navigated`.
   - Cleanup ISOLÉ via gsap.matchMedia().revert() : ne touche QUE nos triggers,
     jamais ceux d'un autre module (ex. home-journey.js sur la home).

   API DÉCLARATIVE (data-attributes) :
     [data-premium-scroll]                 active le smooth scroll Lenis pour la page
     [data-scroll-parallax="0.3"]          parallaxe verticale au scroll
                                           (>0 = plus lent / -1..1 ; adoucie sur mobile)
       [data-scroll-clip] (sur un parent)  recadre le débordement visuel de la parallaxe
     [data-scroll-pin]                     épingle la section pendant le scroll
       [data-scroll-pin-end="+=100%"]      durée d'épinglage (défaut +=100%)
       [data-scroll-pin-start="top top"]   point d'accroche (défaut top top)
     [data-scroll-horizontal]              section à défilement horizontal
       > [data-scroll-track]                 le rail translaté (display:flex)
         > [data-scroll-panel] ...           les panneaux (100vw par défaut)
     [data-scroll-progress]                progression 0..1 -> --scroll-progress sur l'élément
       [data-scroll-progress="#section"]     scope optionnel (défaut = page entière)

   Le reveal-on-scroll générique ([data-cx-reveal]) reste géré par app.js : on ne
   le duplique pas ici.
   ========================================================================= */

const SELECTOR =
    '[data-premium-scroll],[data-scroll-parallax],[data-scroll-pin],[data-scroll-horizontal],[data-scroll-progress]';

const reduceMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Bootstrap synchrone ultra-léger : la page utilise-t-elle le moteur ?
function pageUsesEngine() {
    return !!document.querySelector(SELECTOR);
}

let engine = null; // état courant (singleton) ; null = inactif

/* ----------------------------------------------------------------------------
   Démarrage (asynchrone : charge gsap/ScrollTrigger/lenis à la demande)
   ------------------------------------------------------------------------- */
async function boot() {
    if (engine) return; // déjà actif
    if (!pageUsesEngine()) return; // page non opt-in -> rien

    const root = document.documentElement;
    root.classList.add('has-premium-scroll'); // arme les gardes CSS (will-change, etc.)

    // Reduced motion : on n'anime rien. Le contenu reste en place et lisible,
    // le scroll natif fonctionne. On marque l'état pour le CSS et on s'arrête.
    if (reduceMotion()) {
        root.classList.add('premium-scroll-static');
        engine = { static: true };
        return;
    }

    // Imports dynamiques (code-splittés par Vite ; gsap est partagé avec les
    // autres entrées qui l'importent statiquement).
    let Lenis, gsap, ScrollTrigger;
    try {
        const [lenisMod, gsapMod, stMod] = await Promise.all([
            import('lenis'),
            import('gsap'),
            import('gsap/ScrollTrigger'),
        ]);
        Lenis = lenisMod.default || lenisMod;
        gsap = gsapMod.default || gsapMod.gsap || gsapMod;
        ScrollTrigger = stMod.ScrollTrigger || stMod.default;
    } catch (e) {
        // Lib indisponible -> on retombe sur le scroll natif, page intacte.
        return;
    }
    if (!gsap || !ScrollTrigger) return;
    gsap.registerPlugin(ScrollTrigger);

    const state = { lenis: null, tick: null, mm: null, anchors: null };

    /* ---- Lenis : smooth scroll (uniquement si la page le demande) -------- */
    const wantsSmooth = !!document.querySelector('[data-premium-scroll]');
    const coarse = window.matchMedia('(pointer: coarse)').matches;
    if (wantsSmooth && Lenis) {
        try {
            state.lenis = new Lenis({
                duration: 1.1,
                // easeOutExpo : départ vif, arrivée soyeuse.
                easing: (t) => (t === 1 ? 1 : 1 - Math.pow(2, -10 * t)),
                smoothWheel: true,
                syncTouch: false, // garde le momentum natif au doigt (perf + justesse)
                wheelMultiplier: 1,
            });
            // Pont Lenis <-> ScrollTrigger : scrub parfaitement synchronisé.
            state.lenis.on('scroll', ScrollTrigger.update);
            state.tick = (time) => state.lenis.raf(time * 1000);
            gsap.ticker.add(state.tick);
            gsap.ticker.lagSmoothing(0);

            // Ancres internes : scroll lissé vers la cible (sans casser la nav).
            state.anchors = (ev) => {
                const a = ev.target.closest('a[href^="#"]');
                if (!a) return;
                const id = a.getAttribute('href');
                if (!id || id === '#') return;
                const target = document.querySelector(id);
                if (!target) return;
                ev.preventDefault();
                state.lenis.scrollTo(target, { offset: -72 }); // -hauteur header sticky
            };
            document.addEventListener('click', state.anchors);
        } catch (e) {
            state.lenis = null; // échec d'init -> scroll natif, on continue les behaviors
        }
    }

    /* ---- Behaviors ScrollTrigger, responsives + cleanup isolé ----------- */
    state.mm = gsap.matchMedia();

    state.mm.add(
        {
            reduce: '(prefers-reduced-motion: reduce)',
            wide: '(min-width: 768px)',
        },
        (context) => {
            const { reduce, wide } = context.conditions;
            if (reduce) return; // (re)bascule en reduced-motion -> rien d'animé
            // (Le scroll horizontal reste accessible en reduced-motion via son
            //  fallback natif CSS : scroll-snap tactile + inertie. Cf. .css.)

            buildParallax(gsap, ScrollTrigger, wide);
            buildPins(gsap, ScrollTrigger);
            const cleanupHorizontal = buildHorizontal(gsap, ScrollTrigger, wide);
            buildProgress(gsap, ScrollTrigger);

            // matchMedia revert() (au changement de breakpoint ou au teardown) tue
            // ces triggers et restaure les styles inline. La fonction retournée
            // nettoie en plus les classes posées à la main (.is-pinned-scroll)
            // -> zéro layout shift, et bascule pin <-> natif propre au resize.
            return () => {
                if (cleanupHorizontal) cleanupHorizontal();
            };
        }
    );

    // Les images/polices qui finissent de charger décalent les mesures de pin :
    // on rafraîchit une fois tout chargé.
    state.onLoad = () => ScrollTrigger.refresh();
    window.addEventListener('load', state.onLoad);
    // Filet de sécurité si 'load' est déjà passé.
    requestAnimationFrame(() => ScrollTrigger.refresh());

    state.ScrollTrigger = ScrollTrigger;
    state.gsap = gsap;
    engine = state;
}

/* ----------------------------------------------------------------------------
   Parallaxe — translation verticale liée au scroll (transform = pas de reflow).
   speed > 0 : la couche défile plus lentement (effet "loin / profondeur").
   ------------------------------------------------------------------------- */
function buildParallax(gsap, ScrollTrigger, wide) {
    gsap.utils.toArray('[data-scroll-parallax]').forEach((el) => {
        const raw = parseFloat(el.dataset.scrollParallax);
        const speed = isNaN(raw) ? 0.3 : raw;
        // Amplitude adoucie sur petits écrans (cf. sharp-edge "parallax mobile").
        const amp = (wide ? 1 : 0.5) * speed * 100; // en % de la hauteur de l'élément
        gsap.fromTo(
            el,
            { yPercent: -amp / 2 },
            {
                yPercent: amp / 2,
                ease: 'none',
                scrollTrigger: {
                    trigger: el.closest('[data-scroll-clip]') || el,
                    start: 'top bottom',
                    end: 'bottom top',
                    scrub: true,
                    invalidateOnRefresh: true,
                },
            }
        );
    });
}

/* ----------------------------------------------------------------------------
   Épinglage — la section reste fixe pendant que le scroll la traverse.
   pinSpacing réserve l'espace -> aucun saut de mise en page.
   ------------------------------------------------------------------------- */
function buildPins(gsap, ScrollTrigger) {
    gsap.utils.toArray('[data-scroll-pin]').forEach((el) => {
        // Un conteneur horizontal gère son propre pin : on ne le double pas.
        if (el.hasAttribute('data-scroll-horizontal')) return;
        ScrollTrigger.create({
            trigger: el,
            start: el.dataset.scrollPinStart || 'top top',
            end: el.dataset.scrollPinEnd || '+=100%',
            pin: true,
            pinSpacing: el.dataset.scrollPinSpacing !== 'false',
            anticipatePin: 1,
            invalidateOnRefresh: true,
        });
    });
}

/* ----------------------------------------------------------------------------
   Sections horizontales épinglées — progressive enhancement.
   ----------------------------------------------------------------------------
   - PAR DÉFAUT (mobile, tactile, reduced-motion, sans JS) : scroll horizontal
     NATIF via CSS scroll-snap (overflow-x:auto) -> tactile + inertie native,
     aucun pin-jacking. Toujours accessible. (cf. premium-scroll.css)
   - UPGRADE (desktop, pointeur fin, mouvement OK) : on coupe le scroll natif
     (.is-pinned-scroll) et GSAP ScrollTrigger épingle la section + scrub le
     rail en translateX. L'inertie vient du scrub (et de Lenis si actif).
     Chaque panneau s'anime au passage au centre via containerAnimation :
     scale + translateX + fade. end dynamique + invalidateOnRefresh = responsive.
   Retourne un cleanup qui retire .is-pinned-scroll (les triggers gsap, eux,
   sont revertés par matchMedia).
   ------------------------------------------------------------------------- */
function buildHorizontal(gsap, ScrollTrigger, isDesktop) {
    const coarse = window.matchMedia('(pointer: coarse)').matches;
    const upgraded = [];

    gsap.utils.toArray('[data-scroll-horizontal]').forEach((section) => {
        const track = section.querySelector('[data-scroll-track]') || section;
        const panels = gsap.utils.toArray('[data-scroll-panel]', section);
        if (panels.length < 2) return;

        // Mobile / tactile -> on conserve le scroll natif (snap + inertie).
        if (!isDesktop || coarse) return;

        section.classList.add('is-pinned-scroll');
        upgraded.push(section);

        const hidden = () => Math.max(0, track.scrollWidth - section.clientWidth);

        // Rail : translateX épinglé + scrub (scrub:1 = lissage/inertie).
        const tween = gsap.to(track, {
            x: () => -hidden(),
            ease: 'none',
            scrollTrigger: {
                trigger: section,
                start: 'top top',
                pin: true,
                scrub: 1,
                end: () => '+=' + hidden(),
                anticipatePin: 1,
                invalidateOnRefresh: true,
            },
        });

        // Animation par panneau, pilotée par la position horizontale du rail
        // (containerAnimation) : entrée scale↑ + translateX→0 + fade-in, pic au
        // centre, puis sortie symétrique. transform/opacity only -> 60fps.
        panels.forEach((panel) => {
            const content = panel.querySelector('[data-scroll-panel-inner]') || panel;
            const tl = gsap
                .timeline()
                .fromTo(
                    content,
                    { scale: 0.8, autoAlpha: 0.3, xPercent: 12 },
                    { scale: 1, autoAlpha: 1, xPercent: 0, ease: 'power2.out', duration: 0.5 }
                )
                .to(content, { scale: 0.8, autoAlpha: 0.3, xPercent: -12, ease: 'power2.in', duration: 0.5 });

            ScrollTrigger.create({
                trigger: panel,
                containerAnimation: tween,
                start: 'left right', // bord gauche du panneau entre par la droite
                end: 'right left', // bord droit sort par la gauche
                scrub: true,
                animation: tl,
            });
        });
    });

    return () => upgraded.forEach((s) => s.classList.remove('is-pinned-scroll'));
}

/* ----------------------------------------------------------------------------
   Progression — écrit --scroll-progress (0..1) sur l'élément + ARIA.
   Le CSS rend la barre via transform: scaleX(var(--scroll-progress)) -> pas de reflow.
   Scope optionnel : [data-scroll-progress="#une-section"], sinon page entière.
   ------------------------------------------------------------------------- */
function buildProgress(gsap, ScrollTrigger) {
    gsap.utils.toArray('[data-scroll-progress]').forEach((el) => {
        const scopeSel = el.dataset.scrollProgress;
        const scope = scopeSel ? document.querySelector(scopeSel) : null;
        ScrollTrigger.create({
            trigger: scope || document.body,
            start: scope ? 'top top' : 'top top',
            end: scope ? 'bottom bottom' : 'bottom bottom',
            scrub: true,
            invalidateOnRefresh: true,
            onUpdate: (self) => {
                const p = self.progress;
                el.style.setProperty('--scroll-progress', p.toFixed(4));
                if (el.hasAttribute('role') || el.getAttribute('aria-valuenow') !== null) {
                    el.setAttribute('aria-valuenow', String(Math.round(p * 100)));
                }
            },
        });
    });
}

/* ----------------------------------------------------------------------------
   Cycle de vie
   ------------------------------------------------------------------------- */
function teardown() {
    if (!engine) return;
    const s = engine;
    engine = null;

    if (s.static) {
        document.documentElement.classList.remove('premium-scroll-static');
        return;
    }
    try {
        if (s.mm) s.mm.revert(); // tue NOS triggers + restaure les styles inline
        if (s.onLoad) window.removeEventListener('load', s.onLoad);
        if (s.anchors) document.removeEventListener('click', s.anchors);
        if (s.lenis) {
            if (s.tick) s.gsap.ticker.remove(s.tick);
            s.lenis.destroy();
        }
        if (s.ScrollTrigger) s.ScrollTrigger.refresh();
    } catch (e) {
        /* teardown best-effort */
    }
    document.documentElement.classList.remove('has-premium-scroll');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigating', teardown);
document.addEventListener('livewire:navigated', boot);
window.addEventListener('pagehide', teardown);
