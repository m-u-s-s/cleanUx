// tools/visual-qa/check.mjs
import { CREDENTIALS, QA_PASSWORD } from './modules.mjs';

const TOL = Number(process.env.VQA_TOLERANCE ?? 2);
const VIEWPORT = { width: 390, height: 844 };

/** Connexion via le form Fortify (le navigateur gère le CSRF). */
export async function loginAs(context, base, credKey) {
  if (!credKey) return; // public
  const email = CREDENTIALS[credKey];
  const page = await context.newPage();
  await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', QA_PASSWORD);
  await Promise.all([
    // 'load' (pas 'networkidle') : le dashboard de destination charge Livewire/Alpine
    // et garde des connexions ouvertes — 'networkidle' ne se résoudrait jamais.
    page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 }).catch(() => {}),

    /*
     * `noWaitAfter` : SANS LUI, DEUX ATTENTES SE COURENT APRÈS.
     *
     * `click()` attend de lui-même « les navigations programmées », avec son propre délai de
     * trente secondes — en doublon du `waitForURL` ci-dessus, qui est borné à vingt et dont
     * l'échec est rattrapé. Sur un balayage de 121 pages, le serveur de développement ralentit
     * et c'est le clic qui expire le premier : seize pages du groupe prestataire tombaient en
     * HTTP 0, toutes pour cette raison, et passaient une à une en isolation.
     *
     * L'assertion qui compte — « sommes-nous encore sur /login ? » — est inchangée.
     */
    page.click('button[type="submit"], input[type="submit"]', { noWaitAfter: true }),
  ]);
  await page.waitForLoadState('load', { timeout: 10000 }).catch(() => {});
  const url = page.url();
  await page.close();
  if (url.includes('/login')) {
    throw new Error(`login failed for ${credKey} (${email}) — still on /login`);
  }
}

/** Évalue les 5 critères dans la page. Retourne { c1..c5, offenders }. */
const EVAL = (tol) => {
  const T = tol;
  const out = { criteria: {}, offenders: {} };
  const docEl = document.documentElement;

  // C1 — pas de scroll horizontal au niveau document.
  out.criteria.c1_no_h_scroll = docEl.scrollWidth <= docEl.clientWidth + T;

  // C5 — nav chrome absent en embed.
  out.criteria.c5_nav_chrome_absent = !document.querySelector('[data-chrome="primary-nav"]');

  const vw = docEl.clientWidth;
  const visible = (el) => {
    const s = getComputedStyle(el);
    if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
    const r = el.getBoundingClientRect();
    if (!(r.width > 0 && r.height > 0)) return false;
    // Exclure les éléments visually-hidden / sr-only (skip-to-content, labels a11y) :
    // micro-rect clippé (≤1px) OU positionné hors écran (left négatif extrême,
    // pattern `position:absolute; left:-9999px`). Invisibles à l'œil → hors critères.
    if (r.width <= 1 || r.height <= 1) return false;
    if (r.right <= 0 || r.left >= vw || r.bottom <= 0) return false;
    return true;
  };
  const inScrollable = (el) => {
    // ignore les éléments dans (ou QUI SONT) un conteneur à scroll horizontal
    // intentionnel — y compris l'élément lui-même (un <table> avec overflow-x-auto)
    // et les internes de table (thead/tbody/tr/td/th) sous un wrapper scrollable.
    const TABLE_INTERNAL = new Set(['THEAD', 'TBODY', 'TR', 'TD', 'TH', 'TABLE']);
    let p = el;
    while (p) {
      const ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
      p = p.parentElement;
    }
    // Un élément interne de table est ignoré si sa table-racine déborde dans un
    // wrapper (pattern Tailwind `overflow-x-auto > table`).
    if (TABLE_INTERNAL.has(el.tagName)) {
      const wrapper = el.closest('table')?.parentElement;
      if (wrapper) {
        const ox = getComputedStyle(wrapper).overflowX;
        if (ox === 'auto' || ox === 'scroll') return true;
      }
    }
    return false;
  };

  // C2 — tap targets : seulement les CONTRÔLES primaires (boutons, liens-boutons),
  // pas les liens texte inline (sinon faux positifs massifs).
  // Seuil SIGNAL (calibré sur la baseline 2026-06-01) : on ne flague qu'une cible
  // RÉELLEMENT hostile au pouce — exiguë dans LES DEUX dimensions (icon-button,
  // chip cramponnée), pas un onglet/bouton-texte large mais peu haut. Un onglet
  // admin 374×35 ou un toggle texte 90×21 reste atteignable (touch-slop) → PASS ;
  // un bouton 24×24 ou un chip 70×20 → FAIL.
  const C2_MIN_HEIGHT = 24; // sous 24px = trop fin
  const C2_NARROW = 80; // étroitesse co-requise pour qu'une faible hauteur compte
  const controls = [...document.querySelectorAll(
    'button, [role="button"], input[type="submit"], input[type="button"], a.btn, .ui-btn, .brio-btn-primary, .brio-btn-secondary, .brio-btn-danger'
  )].filter(visible);
  const smallTargets = controls
    .filter((el) => {
      const r = el.getBoundingClientRect();
      // FAIL seulement si exigu dans les DEUX dimensions, ou largeur minuscule (icône).
      return (r.height < C2_MIN_HEIGHT && r.width < C2_NARROW) || r.width < 28;
    })
    .map((el) => ({ tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 40),
                    w: Math.round(el.getBoundingClientRect().width), h: Math.round(el.getBoundingClientRect().height) }));
  out.criteria.c2_tap_targets = smallTargets.length === 0;
  out.offenders.c2 = smallTargets.slice(0, 10);

  // C3 — texte lisible : aucun élément avec clip horizontal NON INTENTIONNEL
  // (scrollWidth>clientWidth). On exclut le clip VOULU :
  //  - `truncate` Tailwind = text-overflow:ellipsis (l'ellipse EST le design, lisible) ;
  //  - tout conteneur à overflow-x scrollable (déjà géré par inScrollable).
  const isIntentionalEllipsis = (el) => {
    const s = getComputedStyle(el);
    return s.textOverflow === 'ellipsis' && (s.overflowX === 'hidden' || s.overflow === 'hidden');
  };
  // C3 ne concerne que du TEXTE lisible : on ignore les éléments sans texte réel
  // (dots/ping décoratifs `flex h-3 w-3`, wrappers d'icônes) dont le scrollWidth
  // déborde à cause d'un enfant animé en position absolute, pas d'un libellé clipé.
  const hasRealText = (el) => (el.textContent || '').replace(/\s+/g, '').length > 0;
  const clipped = [...document.querySelectorAll('p, span, h1, h2, h3, h4, a, button, td, th, li, label')]
    .filter(visible)
    .filter(hasRealText)
    .filter((el) => el.scrollWidth > el.clientWidth + T && !inScrollable(el) && !isIntentionalEllipsis(el))
    .map((el) => ({ tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 40) }));
  out.criteria.c3_readable_text = clipped.length === 0;
  out.offenders.c3 = clipped.slice(0, 10);

  // C4 — layout non cassé : aucun élément débordant à droite du viewport.
  const overflow = [...document.querySelectorAll('body *')]
    .filter(visible)
    .filter((el) => { const s = getComputedStyle(el); return s.position !== 'fixed' && s.position !== 'absolute'; })
    .filter((el) => !inScrollable(el))
    .filter((el) => el.getBoundingClientRect().right > vw + T)
    .map((el) => ({ tag: el.tagName.toLowerCase(), cls: (el.className || '').toString().slice(0, 50),
                    right: Math.round(el.getBoundingClientRect().right) }));
  out.criteria.c4_no_broken_layout = overflow.length === 0;
  out.offenders.c4 = overflow.slice(0, 10);

  return out;
};

export async function checkModule(context, base, mod) {
  const page = await context.newPage();
  await page.setViewportSize(VIEWPORT);
  let httpStatus = 0;
  try {
    // NOTE: 'domcontentloaded' (pas 'networkidle') — les pages Livewire/Alpine
    // gardent des connexions ouvertes (poll wire:poll, websocket Reverb, ApexCharts),
    // donc 'networkidle' ne se résout jamais et fait timeouter toutes les pages JS.
    const resp = await page.goto(`${base}${mod.path}?embed=1`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    httpStatus = resp ? resp.status() : 0;
    // laisser Livewire/Alpine hydrater + poser le layout final (CSS appliqué).
    await page.waitForLoadState('load', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(900);
    const result = await page.evaluate(EVAL, TOL);
    await page.close();
    const pass = Object.values(result.criteria).every(Boolean);
    return { key: mod.key, path: mod.path, role: mod.credKey ?? 'public', http: httpStatus, pass, ...result };
  } catch (e) {
    await page.close();
    return { key: mod.key, path: mod.path, role: mod.credKey ?? 'public', http: httpStatus, pass: false,
             criteria: {}, offenders: {}, error: String(e).slice(0, 200) };
  }
}
