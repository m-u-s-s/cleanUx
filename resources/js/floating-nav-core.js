/* ============================================================================
   Brio — Floating Nav : cœur framework-agnostique.
   ----------------------------------------------------------------------------
   Améliore un <header data-floating-nav> en barre flottante premium :
     · état transparent (en haut) -> solide + blur backdrop (scrollé)  [.is-solid]
     · détection de sens de scroll : cache vers le bas / révèle vers le haut
       [.is-hidden = translateY(-100%)], anti-jitter (delta), rAF-throttlé
     · menu mobile accessible (disclosure modale) : bouton [data-fn-toggle] +
       panneau [data-fn-panel] -> focus trap, Échap, scroll lock, focus restitué

   A11Y :
     · prefers-reduced-motion -> JAMAIS de hide-on-scroll (nav toujours visible) ;
       transparent/solid reste (changement de fond, pas de mouvement).
     · la nav n'est jamais cachée tant qu'un de ses éléments a le focus (focusin)
       ni quand le menu mobile est ouvert.
     · panneau : role=dialog + aria-modal, `inert`+aria-hidden au repos, focus
       déplacé dedans à l'ouverture, piégé, Échap ferme, focus rendu au toggle.

   API (data-attributes, tous optionnels) :
     data-fn-solid-at="24"     seuil px avant l'état solide
     data-fn-hide-after="140"  px parcourus avant d'autoriser le hide
     [data-fn-toggle][aria-controls="<id panel>"]   bouton du menu
     [data-fn-panel] (#<id>)                         panneau mobile
       [data-fn-close]                               élément(s) qui ferment
       data-fn-label-open / data-fn-label-close      libellés aria du toggle

   initFloatingNav(el, options) -> cleanup
   ========================================================================= */

const reduceMotion = () =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const FOCUSABLE =
    'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

function numAttr(el, attr, opt, def) {
    if (opt != null) return opt;
    const v = el.getAttribute(attr);
    const n = v != null ? parseFloat(v) : NaN;
    return Number.isFinite(n) ? n : def;
}

export function initFloatingNav(el, options = {}) {
    if (!el || el.__fnBound) return () => {};
    el.__fnBound = true;

    const solidAt = numAttr(el, 'data-fn-solid-at', options.solidAt, 24);
    const hideAfter = numAttr(el, 'data-fn-hide-after', options.hideAfter, 140);
    const DELTA = 6; // anti-jitter (px)

    const toggle = el.querySelector('[data-fn-toggle]');
    const panel = toggle
        ? document.getElementById(toggle.getAttribute('aria-controls'))
        : el.querySelector('[data-fn-panel]');
    const closers = panel ? Array.from(panel.querySelectorAll('[data-fn-close]')) : [];

    let lastY = window.scrollY || 0;
    let ticking = false;
    let menuOpen = false;
    let focusWithin = false;

    /* ---- État scroll : transparent/solid + hide/reveal ------------------- */
    function apply() {
        ticking = false;
        const y = window.scrollY || 0;
        el.classList.toggle('is-solid', y > solidAt);

        if (reduceMotion() || menuOpen || focusWithin) {
            el.classList.remove('is-hidden'); // jamais caché dans ces cas
            lastY = y;
            return;
        }
        if (y > hideAfter && y > lastY + DELTA) {
            el.classList.add('is-hidden'); // scroll bas -> cache
        } else if (y < lastY - DELTA || y <= solidAt) {
            el.classList.remove('is-hidden'); // scroll haut / sommet -> révèle
        }
        lastY = y;
    }
    function onScroll() {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(apply);
        }
    }
    function onFocusIn() {
        focusWithin = true;
        el.classList.remove('is-hidden');
    }
    function onFocusOut(e) {
        if (!el.contains(e.relatedTarget)) focusWithin = false;
    }

    el.classList.add('fn-armed');
    window.addEventListener('scroll', onScroll, { passive: true });
    el.addEventListener('focusin', onFocusIn);
    el.addEventListener('focusout', onFocusOut);
    apply(); // état initial (gère un chargement déjà scrollé)

    /* ---- Menu mobile : disclosure modale accessible --------------------- */
    let lastFocused = null;
    let savedOverflow = '';
    let savedPad = '';

    function lockScroll(on) {
        const b = document.body;
        if (on) {
            savedOverflow = b.style.overflow;
            savedPad = b.style.paddingRight;
            const sw = window.innerWidth - document.documentElement.clientWidth;
            if (sw > 0) b.style.paddingRight = sw + 'px';
            b.style.overflow = 'hidden';
        } else {
            b.style.overflow = savedOverflow;
            b.style.paddingRight = savedPad;
        }
    }

    function trapFocus(e) {
        const items = Array.from(panel.querySelectorAll(FOCUSABLE)).filter(
            (n) => n.offsetParent !== null || n === document.activeElement
        );
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }
    function onKeydown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeMenu();
        } else if (e.key === 'Tab') {
            trapFocus(e);
        }
    }

    function openMenu() {
        if (!panel || menuOpen) return;
        menuOpen = true;
        lastFocused = document.activeElement;
        el.classList.add('is-menu-open');
        el.classList.remove('is-hidden');
        panel.removeAttribute('inert');
        panel.removeAttribute('aria-hidden');
        panel.classList.add('is-open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute(
                'aria-label',
                toggle.getAttribute('data-fn-label-close') || 'Fermer le menu'
            );
        }
        lockScroll(true);
        document.addEventListener('keydown', onKeydown);
        const first = panel.querySelector(FOCUSABLE);
        (first || panel).focus();
    }
    function closeMenu(returnFocus = true) {
        if (!panel || !menuOpen) return;
        menuOpen = false;
        el.classList.remove('is-menu-open');
        panel.classList.remove('is-open');
        panel.setAttribute('inert', '');
        panel.setAttribute('aria-hidden', 'true');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute(
                'aria-label',
                toggle.getAttribute('data-fn-label-open') || 'Ouvrir le menu'
            );
        }
        lockScroll(false);
        document.removeEventListener('keydown', onKeydown);
        if (returnFocus && lastFocused && lastFocused.focus) lastFocused.focus();
    }

    function onToggleClick() {
        if (menuOpen) closeMenu();
        else openMenu();
    }
    function onCloserClick() {
        closeMenu();
    }
    function onPanelClick(e) {
        // un clic sur un lien navigue -> on ferme (gère aussi les ancres same-page)
        if (e.target.closest('a[href]')) closeMenu(false);
    }

    if (toggle) toggle.addEventListener('click', onToggleClick);
    closers.forEach((c) => c.addEventListener('click', onCloserClick));
    if (panel) panel.addEventListener('click', onPanelClick);

    // Repasse desktop avec le menu ouvert -> on ferme pour éviter un état bloqué.
    const mq = window.matchMedia('(min-width: 768px)');
    const onMq = () => {
        if (mq.matches && menuOpen) closeMenu(false);
    };
    if (mq.addEventListener) mq.addEventListener('change', onMq);
    else mq.addListener(onMq);

    /* ---- Cleanup -------------------------------------------------------- */
    return function cleanup() {
        window.removeEventListener('scroll', onScroll);
        el.removeEventListener('focusin', onFocusIn);
        el.removeEventListener('focusout', onFocusOut);
        if (toggle) toggle.removeEventListener('click', onToggleClick);
        closers.forEach((c) => c.removeEventListener('click', onCloserClick));
        if (panel) panel.removeEventListener('click', onPanelClick);
        document.removeEventListener('keydown', onKeydown);
        if (mq.removeEventListener) mq.removeEventListener('change', onMq);
        else mq.removeListener(onMq);
        if (menuOpen) lockScroll(false);
        el.classList.remove('fn-armed', 'is-solid', 'is-hidden', 'is-menu-open');
        el.__fnBound = false;
    };
}

export default initFloatingNav;
