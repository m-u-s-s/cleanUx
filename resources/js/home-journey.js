/* ============================================================================
   Brio — Home « Parcours d'une mission » : LOADER léger (lazy).
   ----------------------------------------------------------------------------
   La section 3D cinématique (globe Three.js + GSAP ScrollTrigger) est SOUS la
   ligne de flottaison. On ne charge donc PAS three.js/gsap au chargement de la
   home : ce loader minuscule observe la section et n'importe le cœur
   (home-journey-core.js → three + gsap) que lorsqu'elle APPROCHE du viewport
   (rootMargin ~700px), hors du chemin critique LCP.

   ROBUSTESSE (préservée) :
     • prefers-reduced-motion → on ne charge jamais la 3D (fallback stepper CSS).
     • Pas d'IntersectionObserver → on charge à l'idle (dégradation propre).
     • Re-init après navigation Livewire ; teardown avant de quitter la page.
   ========================================================================= */

let core = null; // module cœur une fois chargé
let observer = null;

function reduceMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function loadCore() {
    if (core) {
        core.init();
        return;
    }
    import('./home-journey-core')
        .then((m) => {
            core = m;
            core.init();
        })
        .catch(() => {
            /* échec de chargement -> le scrollytelling SVG/stepper reste en place */
        });
}

function observe() {
    const section = document.querySelector('[data-cx-journey]');
    if (!section) return;
    if (reduceMotion()) return; // 3D jamais chargée -> stepper statique

    // Déjà chargé (revenu sur la home en SPA) -> ré-init direct.
    if (core) {
        core.init();
        return;
    }

    if (observer) return; // déjà en attente -> pas de doublon (boot peut refirer)

    if (!('IntersectionObserver' in window)) {
        // Pas d'IO : on charge à l'idle pour ne pas bloquer le rendu initial.
        if ('requestIdleCallback' in window) window.requestIdleCallback(loadCore, { timeout: 3000 });
        else setTimeout(loadCore, 600);
        return;
    }

    // `io` local : le callback ne déréférence jamais la variable de module
    // (qui peut être remise à null par teardown entre-temps).
    const io = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                io.disconnect();
                if (observer === io) observer = null;
                loadCore();
            }
        },
        { rootMargin: '700px 0px 700px 0px' } // démarre le chargement ~1 écran avant
    );
    observer = io;
    io.observe(section);
}

function boot() {
    observe();
}

function teardown() {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
    if (core) core.teardown();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:navigating', teardown);
