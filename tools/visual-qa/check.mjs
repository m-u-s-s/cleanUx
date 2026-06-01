// tools/visual-qa/check.mjs
import { CREDENTIALS, QA_PASSWORD } from './modules.mjs';

const TOL = Number(process.env.VQA_TOLERANCE ?? 2);
const VIEWPORT = { width: 390, height: 844 };

/** Connexion via le form Fortify (le navigateur gère le CSRF). */
export async function loginAs(context, base, credKey) {
  if (!credKey) return; // public
  const email = CREDENTIALS[credKey];
  const page = await context.newPage();
  await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', QA_PASSWORD);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('button[type="submit"], input[type="submit"]'),
  ]);
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
    return r.width > 0 && r.height > 0;
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
    'button, [role="button"], input[type="submit"], input[type="button"], a.btn, .ui-btn, .cu-btn-primary, .cu-btn-secondary, .cu-btn-danger'
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

  // C3 — texte lisible : aucun élément avec clip horizontal (scrollWidth>clientWidth).
  const clipped = [...document.querySelectorAll('p, span, h1, h2, h3, h4, a, button, td, th, li, label')]
    .filter(visible)
    .filter((el) => el.scrollWidth > el.clientWidth + T && !inScrollable(el))
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
    const resp = await page.goto(`${base}${mod.path}?embed=1`, { waitUntil: 'networkidle', timeout: 30000 });
    httpStatus = resp ? resp.status() : 0;
    // laisser Livewire/JS poser le layout
    await page.waitForTimeout(400);
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
