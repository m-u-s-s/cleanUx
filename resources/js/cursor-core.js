/* ============================================================================
   Brio — Premium Cursor : cœur framework-agnostique.
   ----------------------------------------------------------------------------
   Curseur custom premium piloté par UN seul requestAnimationFrame partagé :
     · follower deux couches  — un point (rapide) + un anneau (traîne, lerp)
     · boutons magnétiques    — [data-magnetic] s'incline vers le curseur,
                                l'anneau « accroche » son centre
     · états de survol         — l'anneau grossit sur a/button/[data-cursor-hover],
                                label optionnel via [data-cursor-text]
     · aperçu image            — [data-cursor-image="/x.jpg"] fait flotter une
                                vignette qui suit le curseur (reveal clip-path)
     · interpolation douce     — lerp (current += (target-current)*ease)

   PERFS : une boucle rAF unique, qui s'ENDORT quand tout est stabilisé et se
   RÉVEILLE au mouvement (économie batterie). Survol/magnétique/aperçu passent
   par de la DÉLÉGATION d'événements sur `document` → fonctionne sur les éléments
   ajoutés dynamiquement (Livewire) sans re-scan.

   PROGRESSIVE ENHANCEMENT / A11Y :
     · activé seulement si (any-pointer: fine) ET pas prefers-reduced-motion ;
       sinon noop total -> le curseur natif reste, aucun coût.
     · le curseur natif n'est masqué (.cursor-armed) qu'une fois le JS armé et
       APRÈS le premier mouvement souris -> hybrides tactile/trackpad OK
       (un événement pointer `touch` ré-affiche le natif).
     · champs de saisie : curseur natif (I-beam) préservé (géré en CSS).

   initPremiumCursor(options) -> cleanup()   (singleton ; second appel = noop)
   ========================================================================= */

const lerp = (a, b, t) => a + (b - a) * t;

const prefersReduce = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const hasFinePointer = () =>
    typeof window !== 'undefined' && window.matchMedia('(any-pointer: fine)').matches;

// Éléments qui font grossir l'anneau (état survol).
const HOVER_SEL =
    'a, button, [role="button"], label, summary, select, .cx-btn, [data-cursor-hover]';

// Champs de saisie : on rend la main au curseur natif (I-beam), cf. CSS.
const FIELD_SEL =
    'input:not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]),' +
    'textarea,[contenteditable=""],[contenteditable="true"]';

const closest = (el, sel) => (el && el.closest ? el.closest(sel) : null);

export function initPremiumCursor(options = {}) {
    if (typeof window === 'undefined' || typeof document === 'undefined') return () => {};
    // Pas de pointeur fin OU reduced-motion -> on ne fait rien (curseur natif).
    if (!hasFinePointer() || prefersReduce()) return () => {};
    // Singleton : un seul curseur par document.
    if (document.documentElement.__pcursorBound) return () => {};
    document.documentElement.__pcursorBound = true;

    const cfg = {
        dotEase: 0.35, // suivi du point (rapide)
        ringEase: 0.18, // traîne de l'anneau
        magnetEase: 0.22, // retour/poursuite des boutons magnétiques
        previewEase: 0.14, // suivi de la vignette
        ringStick: 0.32, // accroche de l'anneau au centre d'un bouton magnétique
        magnetStrength: 0.35, // amplitude magnétique par défaut (0..1)
        innerRatio: 0.4, // un [data-magnetic-inner] bouge un peu plus (profondeur)
        ...options,
    };

    /* ---- DOM (injecté une fois sur <body>) ------------------------------- */
    const root = document.createElement('div');
    root.className = 'pcursor is-inactive';
    root.setAttribute('aria-hidden', 'true');
    root.innerHTML =
        '<div class="pcursor__ring"></div>' +
        '<div class="pcursor__dot"></div>' +
        '<div class="pcursor__label"><span></span></div>' +
        '<figure class="pcursor__preview"><img alt="" decoding="async"></figure>';
    document.body.appendChild(root);

    const ring = root.querySelector('.pcursor__ring');
    const dot = root.querySelector('.pcursor__dot');
    const labelEl = root.querySelector('.pcursor__label');
    const labelText = labelEl.querySelector('span');
    const preview = root.querySelector('.pcursor__preview');
    const previewImg = preview.querySelector('img');

    /* ---- État ------------------------------------------------------------ */
    let tx = window.innerWidth / 2,
        ty = window.innerHeight / 2; // cible (souris)
    let dx = tx, dy = ty; // point
    let rx = tx, ry = ty; // anneau
    let px = tx, py = ty; // aperçu

    let active = false; // curseur visible (armé après 1er move souris)
    let curHover = null;
    let curMagnet = null; // bouton magnétique survolé
    let activeMagnet = null; // === curMagnet (séparé : peut être null pendant le relâchement)
    let curPreview = null;
    let previewOn = false;

    // el -> { cx, cy, strength, inner } pour le magnétisme (poursuite + retour).
    const magnets = new Map();

    let raf = 0;
    let awake = false;

    function wake() {
        if (!awake) {
            awake = true;
            raf = requestAnimationFrame(frame);
        }
    }

    /* ---- Boucle rAF unique ---------------------------------------------- */
    function frame() {
        // Point (suivi rapide).
        dx = lerp(dx, tx, cfg.dotEase);
        dy = lerp(dy, ty, cfg.dotEase);

        // Cible de l'anneau : la souris, sauf accroche sur bouton magnétique.
        let rtx = tx, rty = ty;
        if (activeMagnet) {
            const r = activeMagnet.getBoundingClientRect();
            rtx = lerp(tx, r.left + r.width / 2, cfg.ringStick);
            rty = lerp(ty, r.top + r.height / 2, cfg.ringStick);
        }
        rx = lerp(rx, rtx, cfg.ringEase);
        ry = lerp(ry, rty, cfg.ringEase);

        dot.style.transform = `translate3d(${dx.toFixed(2)}px,${dy.toFixed(2)}px,0) translate(-50%,-50%)`;
        ring.style.transform = `translate3d(${rx.toFixed(2)}px,${ry.toFixed(2)}px,0) translate(-50%,-50%)`;
        labelEl.style.transform = `translate3d(${rx.toFixed(2)}px,${ry.toFixed(2)}px,0) translate(-50%,-50%)`;

        // Boutons magnétiques (poursuite si actif, retour à 0 sinon).
        let magnetsMoving = false;
        magnets.forEach((m, el) => {
            let gx = 0, gy = 0;
            if (el === activeMagnet) {
                // Centre AU REPOS = centre mesuré - translation déjà appliquée.
                // (getBoundingClientRect inclut `translate` -> sans ce retrait, on
                //  crée une contre-réaction qui amortit l'aimant à ~1/3 de sa force.)
                const r = el.getBoundingClientRect();
                const restCx = r.left + r.width / 2 - m.cx;
                const restCy = r.top + r.height / 2 - m.cy;
                gx = (tx - restCx) * m.strength;
                gy = (ty - restCy) * m.strength;
            }
            m.cx = lerp(m.cx, gx, cfg.magnetEase);
            m.cy = lerp(m.cy, gy, cfg.magnetEase);
            el.style.translate = `${m.cx.toFixed(2)}px ${m.cy.toFixed(2)}px`;
            if (m.inner) {
                m.inner.style.translate = `${(m.cx * cfg.innerRatio).toFixed(2)}px ${(m.cy * cfg.innerRatio).toFixed(2)}px`;
            }
            const settled = Math.abs(m.cx - gx) < 0.05 && Math.abs(m.cy - gy) < 0.05;
            if (!settled) {
                magnetsMoving = true;
            } else if (el !== activeMagnet) {
                // Relâché et stabilisé -> on nettoie les styles inline.
                el.style.translate = '';
                if (m.inner) m.inner.style.translate = '';
                magnets.delete(el);
            }
        });

        // Aperçu image (suit la souris en douceur tant qu'il est visible).
        let previewMoving = false;
        if (previewOn) {
            px = lerp(px, tx, cfg.previewEase);
            py = lerp(py, ty, cfg.previewEase);
            preview.style.transform = `translate3d(${px.toFixed(2)}px,${py.toFixed(2)}px,0) translate(-50%,-50%)`;
            previewMoving = Math.abs(px - tx) > 0.2 || Math.abs(py - ty) > 0.2;
        }

        const ringSettled = Math.abs(rx - rtx) < 0.1 && Math.abs(ry - rty) < 0.1;
        const dotSettled = Math.abs(dx - tx) < 0.1 && Math.abs(dy - ty) < 0.1;

        if (ringSettled && dotSettled && !magnetsMoving && !previewMoving) {
            awake = false; // tout est stabilisé -> on dort jusqu'au prochain mouvement
            return;
        }
        raf = requestAnimationFrame(frame);
    }

    /* ---- Survol / magnétique / aperçu : délégation ----------------------- */
    function setHover(el) {
        curHover = el;
        root.classList.add('is-hover');
        const variant = el.getAttribute('data-cursor');
        if (variant) root.dataset.variant = variant;
        const text = el.getAttribute('data-cursor-text');
        if (text) {
            labelText.textContent = text;
            root.classList.add('is-label');
        }
    }
    function clearHover() {
        curHover = null;
        root.classList.remove('is-hover', 'is-label');
        delete root.dataset.variant;
    }

    function setMagnet(el) {
        curMagnet = el;
        activeMagnet = el;
        if (!magnets.has(el)) {
            const s = parseFloat(el.getAttribute('data-magnetic-strength'));
            magnets.set(el, {
                cx: 0,
                cy: 0,
                strength: Number.isFinite(s) ? s : cfg.magnetStrength,
                inner: el.querySelector('[data-magnetic-inner]'),
            });
        }
        wake();
    }
    function releaseMagnet() {
        curMagnet = null;
        activeMagnet = null; // la cible passe à 0 -> retour amorti dans la boucle
        wake();
    }

    function setPreview(el) {
        const src = el.getAttribute('data-cursor-image');
        if (!src) return;
        curPreview = el;
        previewImg.src = src;
        previewOn = true;
        // On démarre la vignette à la position souris (pas de vol depuis l'ancienne).
        px = tx;
        py = ty;
        preview.classList.add('is-visible');
        root.classList.add('is-preview');
        wake();
    }
    function clearPreview() {
        curPreview = null;
        previewOn = false;
        preview.classList.remove('is-visible');
        root.classList.remove('is-preview');
    }

    /* ---- Handlers -------------------------------------------------------- */
    function setActive(on) {
        if (on === active) return;
        active = on;
        root.classList.toggle('is-inactive', !on);
        document.documentElement.classList.toggle('cursor-armed', on);
    }

    function onPointerMove(e) {
        // Tactile sur appareil hybride -> on rend la main au curseur natif.
        if (e.pointerType === 'touch') {
            setActive(false);
            return;
        }
        if (!active) setActive(true);
        root.classList.remove('is-out');
        tx = e.clientX;
        ty = e.clientY;
        wake();
    }

    function onOver(e) {
        if (e.pointerType === 'touch') return;
        const hov = closest(e.target, HOVER_SEL);
        if (hov && hov !== curHover) setHover(hov);
        const mag = closest(e.target, '[data-magnetic]');
        if (mag && mag !== curMagnet) setMagnet(mag);
        const img = closest(e.target, '[data-cursor-image]');
        if (img && img !== curPreview) setPreview(img);
        if (closest(e.target, FIELD_SEL)) root.classList.add('is-field');
    }

    function onOut(e) {
        if (e.pointerType === 'touch') return;
        const to = e.relatedTarget;
        if (curHover && closest(to, HOVER_SEL) !== curHover) clearHover();
        if (curMagnet && closest(to, '[data-magnetic]') !== curMagnet) releaseMagnet();
        if (curPreview && closest(to, '[data-cursor-image]') !== curPreview) clearPreview();
        if (root.classList.contains('is-field') && !closest(to, FIELD_SEL)) {
            root.classList.remove('is-field');
        }
    }

    // Souris quittant la fenêtre -> on masque le curseur custom.
    function onDocLeave(e) {
        if (e.relatedTarget == null && e.toElement == null) root.classList.add('is-out');
    }
    function onDocEnter() {
        root.classList.remove('is-out');
    }

    document.addEventListener('pointermove', onPointerMove, { passive: true });
    document.addEventListener('pointerover', onOver, { passive: true });
    document.addEventListener('pointerout', onOut, { passive: true });
    document.addEventListener('mouseleave', onDocLeave);
    document.addEventListener('mouseenter', onDocEnter);

    /* ---- Cleanup --------------------------------------------------------- */
    return function cleanup() {
        cancelAnimationFrame(raf);
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerover', onOver);
        document.removeEventListener('pointerout', onOut);
        document.removeEventListener('mouseleave', onDocLeave);
        document.removeEventListener('mouseenter', onDocEnter);
        magnets.forEach((m, el) => {
            el.style.translate = '';
            if (m.inner) m.inner.style.translate = '';
        });
        magnets.clear();
        root.remove();
        document.documentElement.classList.remove('cursor-armed');
        delete document.documentElement.__pcursorBound;
    };
}

export default initPremiumCursor;
