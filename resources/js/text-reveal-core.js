/* ============================================================================
   Brio — Cinematic Text Reveal : cœur framework-agnostique.
   ----------------------------------------------------------------------------
   Moteur partagé entre le hook React (resources/js/hooks/useTextReveal.js) et
   les intégrations vanilla (ex. le hero de la home dans luxury-hero.js).
   Aucune dépendance d'animation : Web Animations API + IntersectionObserver.

   revealText(el, options) -> fonction de cleanup.

   Deux modes de découpage :
   - AUTO (défaut) : splitText() découpe le texte brut en lignes/mots.
   - EXISTANT : options.lines = sélecteur | NodeList | élément[] -> on révèle un
     markup de lignes déjà présent (ex. <span class="cx-line"><span>…</span></span>
     du hero). Le markup n'est PAS détruit ; chaque masque fourni doit déjà
     rogner son débordement (overflow:hidden). On force juste l'inner en
     .ctr-line__inner (display:block — requis pour que transform/clip s'appliquent
     sur ce qui serait sinon un span inline).

   Options : granularity, lines, trigger ('inview'|'immediate'), threshold, once,
   stagger, duration, delay, distance (em), clip, easing.
   prefers-reduced-motion -> aucun découpage ni animation (texte visible).
   ========================================================================= */
import { splitText, restoreText } from './hooks/splitText';

export function revealText(el, options = {}) {
    if (!el) return () => {};

    const {
        granularity = 'line',
        lines = null,
        trigger = 'inview',
        threshold = 0.25,
        once = true,
        stagger = 0.11,
        duration = 0.9,
        delay = 0,
        distance = '0.4em',
        clip = true,
        easing = 'cubic-bezier(0.16, 1, 0.3, 1)',
    } = options;

    // Reduced motion : on ne touche à rien, le texte reste visible.
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        el.style.visibility = '';
        return () => {};
    }

    let targets = [];
    let usedSplit = false;
    let animations = [];
    let io = null;
    let ro = null;
    let resizeTimer = null;
    let disposed = false;
    let played = false;
    let lastWidth = el.clientWidth;

    const resolveExistingLines = () => {
        if (typeof lines === 'string') return Array.from(el.querySelectorAll(lines));
        if (lines && lines.length != null) return Array.from(lines);
        if (lines) return [lines];
        return null;
    };

    const keyframes = () => [
        {
            opacity: 0,
            transform: `translateY(${distance})`,
            ...(clip ? { clipPath: 'inset(0 0 100% 0)' } : null),
        },
        {
            opacity: 1,
            transform: 'translateY(0)',
            ...(clip ? { clipPath: 'inset(0 0 -8% 0)' } : null),
        },
    ];

    const setHidden = () => {
        targets.forEach((t) => {
            t.style.opacity = '0';
            t.style.transform = `translateY(${distance})`;
            if (clip) t.style.clipPath = 'inset(0 0 100% 0)';
        });
    };

    const setShown = () => {
        targets.forEach((t) => {
            t.style.opacity = '';
            t.style.transform = '';
            if (clip) t.style.clipPath = '';
        });
    };

    const cancelAll = () => {
        animations.forEach((a) => a.cancel());
        animations = [];
    };

    const play = () => {
        if (played && once) return;
        played = true;
        cancelAll();
        const kf = keyframes();
        targets.forEach((t, i) => {
            animations.push(
                t.animate(kf, {
                    duration: duration * 1000,
                    delay: (delay + i * stagger) * 1000,
                    easing,
                    fill: 'both',
                })
            );
        });
    };

    const build = () => {
        const existing = resolveExistingLines();
        if (existing && existing.length) {
            usedSplit = false;
            el.classList.add('ctr-split');
            targets = existing.map((mask) => {
                const inner = mask.firstElementChild || mask;
                inner.classList.add('ctr-line__inner');
                return inner;
            });
        } else {
            usedSplit = true;
            targets = splitText(el, granularity).targets;
        }
        el.style.visibility = '';
        setHidden();
    };

    const restore = () => {
        cancelAll();
        if (usedSplit) {
            restoreText(el);
        } else {
            el.classList.remove('ctr-split');
            targets.forEach((t) => {
                t.classList.remove('ctr-line__inner');
                t.style.opacity = '';
                t.style.transform = '';
                if (clip) t.style.clipPath = '';
            });
        }
    };

    // Re-découpe + ré-applique l'état courant (resize / swap de police).
    // Pertinent seulement en mode AUTO (markup existant = lignes figées).
    const rebuild = () => {
        if (disposed || !usedSplit) return;
        cancelAll();
        restoreText(el);
        build();
        if (played) setShown();
        else if (trigger === 'immediate') play();
    };

    build();

    if (usedSplit && typeof document !== 'undefined' && document.fonts && document.fonts.status !== 'loaded') {
        document.fonts.ready.then(() => rebuild());
    }

    if (trigger === 'immediate') {
        play();
    } else {
        io = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        play();
                        if (once && io) {
                            io.disconnect();
                            io = null;
                        }
                    } else if (!once) {
                        played = false;
                        cancelAll();
                        setHidden();
                    }
                });
            },
            { threshold }
        );
        io.observe(el);
    }

    if (usedSplit) {
        ro = new ResizeObserver((entries) => {
            const width = Math.round(entries[0].contentRect.width);
            if (width === lastWidth) return;
            lastWidth = width;
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(rebuild, 150);
        });
        ro.observe(el);
    }

    return () => {
        disposed = true;
        if (io) io.disconnect();
        if (ro) ro.disconnect();
        if (resizeTimer) clearTimeout(resizeTimer);
        restore();
    };
}

export default revealText;
