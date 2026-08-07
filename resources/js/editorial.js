/* ============================================================================
   Brio — Editorial study : entrée JS (page dev /editorial).
   ----------------------------------------------------------------------------
   Câble le MOUVEMENT de la page éditoriale, en réutilisant les moteurs partagés :
     • titres cinématiques : revealText() (cœur text-reveal partagé) sur les
       [data-ed-lines] (révèle un markup de lignes .ed-line existant → SEO/LCP OK).
     • révélations d'entrée génériques : IntersectionObserver -> .is-in
       (fade-up + blur, jamais de listener scroll).
     • compteurs : [data-ed-count] animés à l'entrée (rAF, ease-out cubic).
   (Parallaxe, progression, curseur, nav, image-reveal sont gérés par leurs
   propres moteurs via data-attributes dans le Blade.)
   prefers-reduced-motion : tout est rendu visible/final sans animation.
   ========================================================================= */
import { revealText } from './text-reveal-core';

const reduce = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---- 1 · Titres cinématiques (lignes pré-découpées dans le Blade) -------- */
function initHeadings(root) {
    root.querySelectorAll('[data-ed-lines]:not([data-ed-lines-bound])').forEach((el) => {
        el.setAttribute('data-ed-lines-bound', '1');
        revealText(el, {
            lines: el.querySelectorAll('.ed-line'),
            stagger: 0.1,
            duration: 0.9,
            distance: '0.5em',
        });
    });
}

/* ---- 2 · Révélations d'entrée génériques --------------------------------- */
let io = null;
function initReveals(root) {
    const els = root.querySelectorAll('[data-ed-reveal]:not([data-ed-reveal-bound])');
    if (!els.length) return;

    if (reduce() || !('IntersectionObserver' in window)) {
        els.forEach((el) => {
            el.setAttribute('data-ed-reveal-bound', '1');
            el.classList.add('is-in');
        });
        return;
    }
    if (!io) {
        io = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        e.target.classList.add('is-in');
                        io.unobserve(e.target);
                    }
                });
            },
            { threshold: 0.16, rootMargin: '0px 0px -8% 0px' }
        );
    }
    els.forEach((el) => {
        el.setAttribute('data-ed-reveal-bound', '1');
        io.observe(el);
    });
}

/* ---- 3 · Compteurs ------------------------------------------------------- */
function runCount(el) {
    const target = parseFloat(el.dataset.edCount);
    if (Number.isNaN(target)) return;
    const decimals = (el.dataset.edCount.split('.')[1] || '').length;
    const prefix = el.dataset.edPrefix || '';
    const suffix = el.dataset.edSuffix || '';
    const fmt = (v) =>
        prefix +
        new Intl.NumberFormat(document.documentElement.lang || undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(v) +
        suffix;

    if (reduce()) {
        el.textContent = fmt(target);
        return;
    }
    const dur = 1500;
    let start = null;
    function frame(ts) {
        if (start === null) start = ts;
        const p = Math.min((ts - start) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = fmt(target * eased);
        if (p < 1) requestAnimationFrame(frame);
        else el.textContent = fmt(target);
    }
    requestAnimationFrame(frame);
}
let countIo = null;
function initCounts(root) {
    const els = root.querySelectorAll('[data-ed-count]:not([data-ed-count-bound])');
    if (!els.length) return;
    if (reduce() || !('IntersectionObserver' in window)) {
        els.forEach((el) => { el.setAttribute('data-ed-count-bound', '1'); runCount(el); });
        return;
    }
    if (!countIo) {
        countIo = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) { runCount(e.target); countIo.unobserve(e.target); }
                });
            },
            { threshold: 0.5 }
        );
    }
    els.forEach((el) => { el.setAttribute('data-ed-count-bound', '1'); countIo.observe(el); });
}

/* ---- 4 · Parallaxe souris (couches flottantes du hero luxe) -------------- */
function initMouseParallax(root) {
    const host = root.querySelector('[data-lux-parallax-root]:not([data-lux-parallax-bound])');
    if (!host) return;
    host.setAttribute('data-lux-parallax-bound', '1');
    // Désactivé en reduced-motion ou sur pointeur grossier (tactile).
    if (reduce() || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    const layers = host.querySelectorAll('[data-lux-parallax]');
    if (!layers.length) return;

    let raf = null;
    let nx = 0;
    let ny = 0;
    function apply() {
        raf = null;
        layers.forEach((l) => {
            const d = parseFloat(l.dataset.luxParallax) || 12;
            l.style.transform = `translate3d(${(nx * d).toFixed(1)}px,${(ny * d).toFixed(1)}px,0)`;
        });
    }
    function queue() { if (!raf) raf = requestAnimationFrame(apply); }
    host.addEventListener('pointermove', (e) => {
        const r = host.getBoundingClientRect();
        nx = (e.clientX - r.left) / r.width - 0.5; // -0.5 .. 0.5
        ny = (e.clientY - r.top) / r.height - 0.5;
        queue();
    });
    host.addEventListener('pointerleave', () => { nx = 0; ny = 0; queue(); });
}

/* ---- Boot ---------------------------------------------------------------- */
function boot() {
    const root = document.querySelector('.ed') || document;
    initHeadings(root);
    initReveals(root);
    initCounts(root);
    initMouseParallax(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
