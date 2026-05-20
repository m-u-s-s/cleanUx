import './bootstrap';
import './echo-listeners';
import './cleanux-mission-tracking';
import './assistant-streaming';
import './fullcalendar';
import './push-notifications';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
import './pwa';

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

window.FullCalendar = {
    Calendar,
    plugins: [dayGridPlugin, interactionPlugin],
};

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
                    e.target.classList.add('cx-in');
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