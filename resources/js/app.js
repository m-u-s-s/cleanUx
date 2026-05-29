import './bootstrap';
import './echo-listeners';
import './cleanux-mission-tracking';
import './assistant-streaming';
import './push-notifications';
import './pwa';

// FullCalendar (resources/js/fullcalendar.js → window.cleanuxFC) and ApexCharts
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