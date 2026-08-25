import './bootstrap';
import './echo-listeners';
import './brio-mission-tracking';
import './assistant-streaming';
import './push-notifications';
import './pwa';

/**
 * LE POINT D'ENTREE DES NOTIFICATIONS, DEPUIS DU JAVASCRIPT ORDINAIRE.
 *
 * `<x-toast />` n'ecoutait que `Livewire.on('toast')`. Un script de vue — celui qui suit une
 * position, celui qui valide un code de fin — n'a aucun composant Livewire sous la main : neuf
 * d'entre eux appelaient donc `alert()`, la boite du navigateur, qui ignore le theme, BLOQUE
 * le fil et se signale hors de la fenetre sur mobile.
 *
 * `echo-listeners.js` APPELAIT DEJA CETTE FONCTION sans que personne ne la definisse : la
 * condition etait toujours fausse, et les notifications temps reel retombaient sur l'API
 * `Notification` du systeme — donc sur une permission que presque personne n'accorde.
 *
 * Les trois formes deja employees dans le depot sont acceptees :
 *
 *     window.brioToast('Mission demarree.')
 *     window.brioToast({ message: 'Code invalide.', type: 'error' })
 *     window.brioToast({ title: 'Tache assignee', body: '...', type: 'info' })
 */
window.brioToast = (charge = {}, typeParDefaut = 'success') => {
    const texte =
        typeof charge === 'string'
            ? charge
            : [charge.title, charge.body].filter(Boolean).join(' — ')
              || charge.message
              || charge.msg
              || '';

    if (!texte) return;

    const type = typeof charge === 'string' ? typeParDefaut : charge.type || typeParDefaut;

    window.dispatchEvent(new CustomEvent('brio-toast', { detail: { message: texte, type } }));
};

// Lit un jeton du systeme de design depuis le CSS.
// Un graphique, une carte ou un canevas ne peuvent pas ecrire `var(--x)` : ils lisent ici.
window.brioJeton = (nom, repli = '') => {
    const v = getComputedStyle(document.documentElement).getPropertyValue(nom).trim();

    return v || repli;
};


// FullCalendar (resources/js/fullcalendar.js → window.brioFC) and ApexCharts
// (resources/js/apexcharts.js → window.ApexCharts) are NO LONGER bundled globally.
// They were ~heavy and loaded on every page. They are now dedicated Vite entries
// loaded only on the pages that use them, via @push('scripts') @vite([...]).
// The previous window.FullCalendar global was dead code (the only new FullCalendar.Calendar
// consumers load FullCalendar from CDN).

/* ============================================================================
   Reveal-on-scroll : ajoute la classe `.cx-in` aux éléments [data-cx-reveal]
   quand ils entrent dans le viewport. Utilisable sur N'IMPORTE quelle page.
   - Scroll natif (jamais capturé).
   - prefers-reduced-motion / pas d'IntersectionObserver -> tout visible.
   ========================================================================= */
(function () {
    function init() {
        var els = document.querySelectorAll('[data-cx-reveal]:not(.cx-in)');
        if (!els.length) return;

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('cx-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    var delay = parseInt(e.target.dataset.cxDelay || '0', 10);
                    if (delay > 0) {
                        setTimeout(function () { e.target.classList.add('cx-in'); }, delay);
                    } else {
                        e.target.classList.add('cx-in');
                    }
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });
        els.forEach(function (el) { io.observe(el); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Livewire 3 : ré-initialiser après chaque navigation/rendu de composant
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', init);
})();

/* ============================================================================
   Count-up : anime les compteurs [data-cx-count] de 0 vers leur valeur cible
   quand ils entrent dans le viewport. Conserve préfixe/suffixe (+, ★, etc.).
   - prefers-reduced-motion / pas d'IntersectionObserver -> valeur finale directe.
   ========================================================================= */
(function () {
    function format(value, decimals) {
        return decimals > 0 ? value.toFixed(decimals) : Math.round(value).toString();
    }

    function run(el) {
        var target = parseFloat(el.dataset.cxCount);
        if (isNaN(target)) return;
        var decimals = (el.dataset.cxCount.split('.')[1] || '').length;
        var prefix = el.dataset.cxPrefix || '';
        var suffix = el.dataset.cxSuffix || '';
        var duration = 1300;
        var start = null;

        function frame(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - p, 3); // ease-out cubic
            el.textContent = prefix + format(target * eased, decimals) + suffix;
            if (p < 1) requestAnimationFrame(frame);
            else el.textContent = prefix + format(target, decimals) + suffix;
        }
        requestAnimationFrame(frame);
    }

    function init() {
        var els = document.querySelectorAll('[data-cx-count]:not([data-cx-counted])');
        if (!els.length) return;

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window) || !('requestAnimationFrame' in window)) {
            els.forEach(function (el) {
                var d = (el.dataset.cxCount.split('.')[1] || '').length;
                el.textContent = (el.dataset.cxPrefix || '') + format(parseFloat(el.dataset.cxCount), d) + (el.dataset.cxSuffix || '');
                el.setAttribute('data-cx-counted', '');
            });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.setAttribute('data-cx-counted', '');
                    run(e.target);
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.4 });
        els.forEach(function (el) { io.observe(el); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', init);
})();

/* ============================================================================
   Scrollytelling : "Le parcours d'une mission" ([data-cx-journey])
   La section se fige (sticky) et le scroll fait défiler les 7 scènes.
   - Calcule la progression 0..1 de la section -> --p + scène active.
   - reduced-motion / pas de rAF -> on ne touche à rien (fallback stepper CSS).
   ========================================================================= */
(function () {
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    function init() {
        var section = document.querySelector('[data-cx-journey]');
        if (!section || section.dataset.cxJourneyBound) return;
        // Si la home charge la version cinématique (GSAP + globe 3D), on laisse
        // resources/js/home-journey.js piloter cette section et on s'efface.
        if (section.hasAttribute('data-cx-journey-gsap')) return;

        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('requestAnimationFrame' in window)) return; // laisse le stepper statique

        section.dataset.cxJourneyBound = '1';
        section.classList.add('is-animated');

        var sticky = section.querySelector('.cx-journey__sticky');
        var scenes = section.querySelectorAll('.cx-scene');
        var dots = section.querySelectorAll('.cx-dot');
        var steps = scenes.length;
        var current = 0;
        var ticking = false;

        function setStep(i) {
            if (i === current) return;
            current = i;
            scenes.forEach(function (s, k) { s.classList.toggle('is-active', k === i); });
            dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); });
        }

        function update() {
            ticking = false;
            var rect = section.getBoundingClientRect();
            var total = section.offsetHeight - window.innerHeight;
            var p = clamp(-rect.top / (total || 1), 0, 1);
            sticky.style.setProperty('--p', p.toFixed(4));
            setStep(Math.min(steps - 1, Math.floor(p * steps)));
        }

        function onScroll() {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('livewire:navigated', init);
})();

/* ============================================================================
   Premium interactions : boutons magnétiques · parallaxe pointeur du hero ·
   tilt 3D des cartes. (ajouté 26/06/2026)
   - Vanilla, GPU-only (transform). Aucun framework.
   - Désactivé en prefers-reduced-motion ET sur pointeur grossier/tactile.
   - Ré-initialisé après chaque navigation/rendu Livewire.
   ========================================================================= */
(function () {
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    // Pas d'effets de pointeur si l'utilisateur préfère moins de mouvement
    // ou navigue au doigt (mobile/tablette) : page sobre et rapide.
    if (reduce || !fine) return;

    /* --- Boutons magnétiques : le wrapper [data-cx-magnetic] suit le curseur --- */
    function bindMagnetic() {
        document.querySelectorAll('[data-cx-magnetic]:not([data-cx-magnetic-bound])').forEach(function (el) {
            el.setAttribute('data-cx-magnetic-bound', '1');
            var strength = parseFloat(el.dataset.cxMagnetic) || 0.28;
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var x = (e.clientX - (r.left + r.width / 2)) * strength;
                var y = (e.clientY - (r.top + r.height / 2)) * strength;
                el.style.transform = 'translate(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px)';
            });
            el.addEventListener('pointerleave', function () { el.style.transform = ''; });
        });
    }

    /* --- Parallaxe du hero : les couches [data-cx-parallax] réagissent à la souris --- */
    function bindParallax() {
        var root = document.querySelector('[data-cx-parallax-root]:not([data-cx-parallax-bound])');
        if (!root) return;
        root.setAttribute('data-cx-parallax-bound', '1');
        var layers = root.querySelectorAll('[data-cx-parallax]');
        if (!layers.length) return;
        var raf = null, nx = 0, ny = 0;

        function apply() {
            raf = null;
            layers.forEach(function (l) {
                var d = parseFloat(l.dataset.cxParallax) || 18;
                l.style.transform = 'translate3d(' + (nx * d).toFixed(1) + 'px,' + (ny * d).toFixed(1) + 'px,0)';
            });
        }
        function queue() { if (!raf) raf = requestAnimationFrame(apply); }

        root.addEventListener('pointermove', function (e) {
            var r = root.getBoundingClientRect();
            nx = (e.clientX - r.left) / r.width - 0.5;   // -0.5 .. 0.5
            ny = (e.clientY - r.top) / r.height - 0.5;
            queue();
        });
        root.addEventListener('pointerleave', function () { nx = 0; ny = 0; queue(); });
    }

    /* --- Tilt 3D : les cartes [data-cx-tilt] s'inclinent vers le curseur --- */
    function bindTilt() {
        document.querySelectorAll('[data-cx-tilt]:not([data-cx-tilt-bound])').forEach(function (el) {
            el.setAttribute('data-cx-tilt-bound', '1');
            var max = parseFloat(el.dataset.cxTilt) || 5;
            el.addEventListener('pointermove', function (e) {
                var r = el.getBoundingClientRect();
                var px = (e.clientX - r.left) / r.width - 0.5;
                var py = (e.clientY - r.top) / r.height - 0.5;
                // translateY(-6px) conserve le "lift" de .cx-lift pendant le survol
                el.style.transform =
                    'perspective(900px) rotateX(' + (-py * max).toFixed(2) + 'deg) rotateY(' +
                    (px * max).toFixed(2) + 'deg) translateY(-6px)';
            });
            el.addEventListener('pointerleave', function () { el.style.transform = ''; });
        });
    }

    function init() {
        bindMagnetic();
        bindParallax();
        bindTilt();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', init);
})();