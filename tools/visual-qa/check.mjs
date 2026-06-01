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
    // ignore les éléments dans un conteneur à scroll horizontal intentionnel.
    let p = el.parentElement;
    while (p) {
      const ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
      p = p.parentElement;
    }
    return false;
  };

  // C2 — tap targets : seulement les CONTRÔLES primaires (boutons, liens-boutons),
  // pas les liens texte inline (sinon faux positifs massifs).
  const controls = [...document.querySelectorAll(
    'button, [role="button"], input[type="submit"], input[type="button"], a.btn, .ui-btn, .cu-btn-primary, .cu-btn-secondary, .cu-btn-danger'
  )].filter(visible);
  const smallTargets = controls
    .filter((el) => { const r = el.getBoundingClientRect(); return r.width < 44 || r.height < 44; })
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
