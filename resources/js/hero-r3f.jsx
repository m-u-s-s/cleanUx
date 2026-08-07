/* ============================================================================
   Brio — Home luxury hero : React Three Fiber background mount
   ----------------------------------------------------------------------------
   Monte le fond de particules R3F dans [data-lux-r3f] (hero de la home).
   PERF (chemin critique préservé) :
     • three.js / react-dom / R3F sont chargés en IMPORT DYNAMIQUE, planifié en
       `requestIdleCallback` APRÈS le premier rendu → hors du chemin LCP. Le hero
       affiche immédiatement son dégradé CSS ; les particules apparaissent ensuite
       (fade `.is-r3f`). Aucune perte de fidélité visuelle.
   ROBUSTESSE :
     • prefers-reduced-motion / pas de WebGL → on ne monte rien (dégradé CSS).
     • Save-Data ou très peu de mémoire (deviceMemory ≤ 2) → on ne monte rien.
     • Re-monté après navigation SPA Livewire ; démonté (GPU libéré) avant de
       quitter la page, y compris si l'import dynamique n'a pas encore résolu.
   ========================================================================= */

let root = null;
let mountEl = null;

function supportsWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return !!(
            window.WebGLRenderingContext &&
            (canvas.getContext('webgl') || canvas.getContext('experimental-webgl'))
        );
    } catch (e) {
        return false;
    }
}

// Réseau « data-saver » ou appareil très contraint → on garde le dégradé CSS.
function constrainedDevice() {
    const c = navigator.connection;
    if (c && c.saveData) return true;
    if (typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 2) return true;
    return false;
}

function whenIdle(cb) {
    if ('requestIdleCallback' in window) window.requestIdleCallback(cb, { timeout: 2000 });
    else setTimeout(cb, 200);
}

function mount() {
    const hero = document.querySelector('[data-lux-hero]');
    if (!hero) return;

    const el = hero.querySelector('[data-lux-r3f]');
    if (!el || el.dataset.mounted === '1') return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (!supportsWebGL() || constrainedDevice()) return;

    el.dataset.mounted = '1';
    mountEl = el;

    // Charge le moteur 3D + react-dom à l'idle (hors chemin critique LCP).
    whenIdle(async () => {
        if (el.dataset.mounted !== '1') return; // démonté avant la résolution
        const [{ createRoot }, { default: HeroBackground }] = await Promise.all([
            import('react-dom/client'),
            import('./r3f/HeroBackground'),
        ]);
        if (el.dataset.mounted !== '1') return; // démonté pendant le chargement
        // Palette des particules surchargeable par page : data-lux-colors="#a,#b,#c"
        // (+ data-lux-fog facultatif). Absent → défauts (home Brio inchangé).
        let colors;
        const raw = el.dataset.luxColors;
        if (raw) {
            const [a, b, c] = raw.split(',').map((s) => s.trim());
            if (a && b && c) colors = { a, b, c };
        }
        const fog = el.dataset.luxFog || undefined;
        root = createRoot(el);
        root.render(<HeroBackground colors={colors} fog={fog} />);
        requestAnimationFrame(() => hero.classList.add('is-r3f'));
    });
}

function unmount() {
    if (root) {
        root.unmount();
        root = null;
    }
    if (mountEl) {
        mountEl.dataset.mounted = '';
        mountEl = null;
    }
    document.querySelector('[data-lux-hero]')?.classList.remove('is-r3f');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
document.addEventListener('livewire:navigating', unmount);
document.addEventListener('livewire:navigated', mount);
window.addEventListener('pagehide', unmount);
