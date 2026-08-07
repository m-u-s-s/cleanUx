/* ============================================================================
   Brio — Luxury Hero interactions (dark cinematic)
   ----------------------------------------------------------------------------
   GSAP entrance timeline + Motion (Framer Motion) scroll-driven effects.
   The WebGL background is a separate React Three Fiber island, see
   resources/js/hero-r3f.jsx. Pointer parallax of the DOM layers is handled by
   the shared engine in app.js ([data-cx-parallax-root] / [data-cx-parallax]).

   ROBUSTNESS (mirrors home-journey.js conventions):
   - Bundle loaded ONLY on the home (CSP prod = script-src 'self').
   - prefers-reduced-motion -> no JS animation; the hero is shown static & fully
     legible (CSS guard releases [data-lux-anim] to opacity 1).
   - Re-initialised after Livewire navigation.
   ========================================================================= */

import gsap from 'gsap';
import { animate, scroll } from 'motion';
import { revealText } from './text-reveal-core';

const REDUCE = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let scrollStop = null;
let titleRevealStop = null;

/* ---- Cinematic title reveal (moteur text-reveal partagé) --------------- */
function revealTitle(hero) {
    const title = hero.querySelector('[data-lux-title]');
    if (!title) return;
    // Libère la garde anti-FOUC (.lux-js [data-lux-anim] { opacity: 0 }) sur le
    // conteneur ; ce sont désormais ses lignes (.cx-line) qui partent masquées.
    title.style.opacity = '1';
    // Les masques .cx-line existent déjà (luxury-hero.css : display:block;
    // overflow:hidden). On révèle leurs spans internes ligne par ligne.
    titleRevealStop = revealText(title, {
        lines: '.cx-line',
        trigger: 'immediate',
        stagger: 0.14,
        duration: 1.0,
        delay: 0.08,
        distance: '0.5em',
    });
}

/* ---- GSAP entrance timeline -------------------------------------------- */
function playEntrance(hero) {
    // Le titre est animé par revealTitle() -> on l'exclut de l'entrance générique.
    const anims = Array.prototype.filter.call(
        hero.querySelectorAll('[data-lux-anim]'),
        (el) => !el.hasAttribute('data-lux-title')
    );
    const cards = hero.querySelectorAll('.cx-lux-card');

    const tl = gsap.timeline({ defaults: { ease: 'power3.out', duration: 0.9 } });

    if (anims.length) {
        tl.fromTo(
            anims,
            { y: 34, opacity: 0 },
            { y: 0, opacity: 1, stagger: 0.09 },
            0.1
        );
    }
    if (cards.length) {
        tl.fromTo(
            cards,
            { y: 48, opacity: 0, scale: 0.92 },
            { y: 0, opacity: 1, scale: 1, duration: 1.0, stagger: 0.12, clearProps: 'transform' },
            0.35
        );
    }
    return tl;
}

/* ---- Motion (Framer Motion) scroll-driven effects ---------------------- */
function bindScrollEffects(hero) {
    const indicator = hero.querySelector('.cx-lux-scroll');
    try {
        if (indicator) {
            scrollStop = scroll(
                animate(indicator, { opacity: [1, 0], y: [0, 16] }),
                { target: hero, offset: ['start start', 'end 60%'] }
            );
        }
    } catch (e) {
        /* Motion unavailable -> indicator simply stays put. */
    }
}

/* ---- Lifecycle --------------------------------------------------------- */
function destroy() {
    if (typeof scrollStop === 'function') scrollStop();
    scrollStop = null;
    if (typeof titleRevealStop === 'function') titleRevealStop();
    titleRevealStop = null;
}

function init() {
    const hero = document.querySelector('[data-lux-hero]');
    if (!hero || hero.dataset.luxBound === '1') return;
    hero.dataset.luxBound = '1';

    if (REDUCE) return;

    hero.classList.add('lux-js');   // arms the CSS initial-hidden guard

    revealTitle(hero);
    playEntrance(hero);
    bindScrollEffects(hero);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
document.addEventListener('livewire:navigating', destroy);
document.addEventListener('livewire:navigated', init);
window.addEventListener('pagehide', destroy);
