/* ============================================================================
   CleanUx — Image Reveal : cœur framework-agnostique.
   ----------------------------------------------------------------------------
   Révélation d'images au scroll via IntersectionObserver. Effets composables
   (CSS transitions, déclenchées par la classe .is-revealed) :
     · clip   — clip-path inset (wipe bas→haut)        [sur le <figure>]
     · mask   — masque gradient diagonal               [sur le <figure>]
     · blur   — blur(16px) → net                        [sur le <img>]
     · scale  — scale(1.14) → 1 (zoom-out)              [sur le <img>]
     · fade   — opacity 0 → 1 (baseline, toujours)      [sur le <figure>]
   + parallax (continu, transform translateY sur le <figure>, piloté par un
     unique rAF partagé, GATÉ par la visibilité IO pour les perfs).

   Anti-conflit transform : clip/mask/parallax vivent sur le <figure> (clip-path
   /mask-position = pas transform), blur/scale sur le <img>. Aucune collision.

   Progressive enhancement : l'état initial masqué n'est posé que sous .ir-armed
   (ajoutée par le JS). Sans JS -> image pleinement visible (SEO/LCP). En
   prefers-reduced-motion -> aucune animation, image visible.

   observeImageReveal(el, options) -> cleanup   (un <figure data-image-reveal>)
   initImageReveals(root)          -> cleanup   (scanne le DOM, voie vanilla)
   ========================================================================= */

const prefersReduce = () =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---- Moteur de parallaxe partagé (1 rAF pour tous les éléments visibles) -- */
const parallaxItems = new Set();
let rafScheduled = false;
let listening = false;

function updateParallax() {
    rafScheduled = false;
    const vh = window.innerHeight || 1;
    parallaxItems.forEach((item) => {
        const r = item.el.getBoundingClientRect();
        // position relative au centre du viewport : -1 (bas) .. 0 .. 1 (haut)
        const rel = (r.top + r.height / 2 - vh / 2) / (vh / 2 + r.height / 2);
        const y = -rel * item.distance;
        item.el.style.setProperty('--ir-py', y.toFixed(1) + 'px');
    });
}

function scheduleParallax() {
    if (!rafScheduled) {
        rafScheduled = true;
        requestAnimationFrame(updateParallax);
    }
}

function ensureListening() {
    if (listening || typeof window === 'undefined') return;
    listening = true;
    window.addEventListener('scroll', scheduleParallax, { passive: true });
    window.addEventListener('resize', scheduleParallax, { passive: true });
}

/* ---- Un élément ---------------------------------------------------------- */
export function observeImageReveal(el, options = {}) {
    if (!el || el.__irBound) return () => {};

    const effects = Array.isArray(options.effects)
        ? options.effects
        : options.effects
          ? String(options.effects).split(/\s+/)
          : el.dataset.irEffect
            ? el.dataset.irEffect.split(/\s+/)
            : ['clip', 'fade'];

    const parallax =
        options.parallax != null
            ? parseFloat(options.parallax)
            : el.dataset.irParallax != null
              ? parseFloat(el.dataset.irParallax)
              : 0;

    const once = options.once != null ? !!options.once : el.dataset.irOnce !== 'false';
    const threshold =
        options.threshold != null
            ? options.threshold
            : el.dataset.irThreshold != null
              ? parseFloat(el.dataset.irThreshold)
              : 0.2;
    const rootMargin = options.rootMargin || el.dataset.irRootMargin || '0px 0px -10% 0px';

    el.__irBound = true;
    el.classList.add('ir');
    el.dataset.irEffect = effects.join(' ');

    // Reduced motion / pas d'IO -> image visible, aucune animation.
    if (prefersReduce() || typeof IntersectionObserver === 'undefined') {
        el.classList.add('is-revealed');
        return () => {
            el.__irBound = false;
        };
    }

    el.classList.add('ir-armed'); // arme l'état initial masqué (gardes CSS)

    let pItem = null;
    if (parallax) {
        el.dataset.irParallax = String(parallax);
        pItem = { el, distance: parallax * 120 }; // amplitude (px)
        ensureListening();
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    el.classList.add('is-revealed');
                    if (pItem) {
                        parallaxItems.add(pItem);
                        scheduleParallax();
                    }
                    // si parallax : on garde l'observation pour gater le rAF à la visibilité
                    if (once && !pItem) io.unobserve(el);
                } else {
                    if (pItem) parallaxItems.delete(pItem);
                    if (!once) el.classList.remove('is-revealed');
                }
            });
        },
        { threshold, rootMargin }
    );
    io.observe(el);

    return () => {
        io.disconnect();
        if (pItem) parallaxItems.delete(pItem);
        el.classList.remove('ir-armed', 'is-revealed');
        el.style.removeProperty('--ir-py');
        el.__irBound = false;
    };
}

/* ---- Voie vanilla : scanne [data-image-reveal] -------------------------- */
export function initImageReveals(root) {
    const scope = root || (typeof document !== 'undefined' ? document : null);
    if (!scope) return () => {};
    const cleanups = [];
    scope.querySelectorAll('[data-image-reveal]:not([data-ir-bound])').forEach((el) => {
        el.setAttribute('data-ir-bound', '1');
        cleanups.push(observeImageReveal(el));
    });
    return () => cleanups.forEach((fn) => fn && fn());
}

export default observeImageReveal;
