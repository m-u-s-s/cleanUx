/**
 * Vérification visuelle de « Le Film » (home, section du parcours d'une mission).
 *
 * Mesure ce qui ne se voit pas dans un test PHP : le moteur choisi, la taille RÉELLE du canvas,
 * la légende affichée à chaque chapitre, et le repli en mouvement réduit.
 *
 *   node tools/visual-qa/verif-film.mjs
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const BASE = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000';
const SORTIE = new URL('./out/film/', import.meta.url).pathname.replace(/^\//, '');
mkdirSync(SORTIE, { recursive: true });

const navigateur = await chromium.launch();

async function ouvrir({ width, height, reducedMotion = 'no-preference', theme = 'dark' }) {
    const contexte = await navigateur.newContext({
        viewport: { width, height },
        deviceScaleFactor: 1,
        reducedMotion,
        colorScheme: theme,
    });
    const page = await contexte.newPage();
    const erreurs = [];
    page.on('pageerror', (e) => erreurs.push(String(e).slice(0, 200)));
    page.on('console', (m) => { if (m.type() === 'error') erreurs.push('console: ' + m.text().slice(0, 200)); });

    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
    return { contexte, page, erreurs };
}

async function etat(page) {
    return page.evaluate(() => {
        const s = document.querySelector('[data-cx-film]');
        const c = document.querySelector('[data-cx-film-canvas]');
        const poster = document.querySelector('.cx-film__poster');
        return {
            classes: s ? s.className : null,
            hauteur: s ? s.offsetHeight : null,
            canvas: c ? c.width + 'x' + c.height : null,
            posterOpacite: poster ? getComputedStyle(poster).opacity : null,
            progression: s ? getComputedStyle(s).getPropertyValue('--film-p').trim() : null,
            legende: (document.querySelector('.cx-film__legende.is-active b') || {}).textContent,
            puceActive: [...document.querySelectorAll('[data-cx-film-puce]')].findIndex((p) => p.classList.contains('is-active')),
            debordementH: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        };
    });
}

/* ---------------------------------------------------------------- 1. DESKTOP */
{
    const { contexte, page, erreurs } = await ouvrir({ width: 1440, height: 900 });

    // Le jeton de version dans l'URL des frames : sans lui, un visiteur qui a deja vu le film
    // se voit reservir l'ancien depuis son cache, quel que soit le nombre de rechargements.
    const framesDemandees = [];
    page.on('request', (r) => {
        const u = r.url();
        if (u.includes('/journey-film/') && u.includes('.avif')) framesDemandees.push(u);
    });

    const haut = await page.evaluate(() => {
        const s = document.querySelector('[data-cx-film]');
        return s.getBoundingClientRect().top + window.scrollY;
    });

    await page.evaluate((y) => window.scrollTo({ top: y - 300, behavior: 'instant' }), haut);
    await page.waitForFunction(() => {
        const s = document.querySelector('[data-cx-film]');
        return s && s.classList.contains('is-peint');
    }, null, { timeout: 20000 }).catch(() => {});

    const depart = await etat(page);
    console.log('DESKTOP 1440x900');
    console.log('  moteur      :', depart.classes);
    console.log('  canvas      :', depart.canvas);
    console.log('  hauteur     :', depart.hauteur, 'px');
    console.log('  poster      : opacite', depart.posterOpacite);

    // Quatre arrêts dans le film : début, premier tiers, deux tiers, fin.
    for (const [nom, fraction] of [['01-galaxie', 0.02], ['05-telephone', 0.32], ['09-qr-depart', 0.62], ['13-poignee', 0.90]]) {
        await page.evaluate(([y, f]) => {
            const s = document.querySelector('[data-cx-film]');
            window.scrollTo({ top: y + s.offsetHeight * f, behavior: 'instant' });
        }, [haut, fraction]);
        await page.waitForTimeout(1400);

        const e = await etat(page);
        console.log(`  ${nom.padEnd(14)} p=${e.progression.padEnd(7)} puce=${String(e.puceActive + 1).padStart(2)}  « ${e.legende} »`);
        await page.screenshot({ path: `${SORTIE}desktop-${nom}.png` });
    }

    // Le bloc de telechargement, tout en bas de la section.
    await page.evaluate(() => {
        const b = document.getElementById('telecharger');
        if (b) b.scrollIntoView({ block: 'center', behavior: 'instant' });
    });
    await page.waitForTimeout(900);
    await page.screenshot({ path: `${SORTIE}desktop-telechargement.png` });

    const sansJeton = framesDemandees.filter((u) => !u.includes('?v='));
    console.log('  frames sans jeton de version :', sansJeton.length, sansJeton.length ? '— DEFAUT DE CACHE' : '(bon)');

    const fin = await etat(page);
    console.log('  debordement horizontal :', fin.debordementH ? 'OUI — DEFAUT' : 'non');
    console.log('  erreurs JS  :', erreurs.length ? erreurs.join(' | ') : 'aucune');
    await contexte.close();
}

/* ----------------------------------------------------------------- 2. MOBILE */
{
    const { contexte, page, erreurs } = await ouvrir({ width: 390, height: 844 });

    const haut = await page.evaluate(() => {
        const s = document.querySelector('[data-cx-film]');
        return s.getBoundingClientRect().top + window.scrollY;
    });
    await page.evaluate((y) => window.scrollTo({ top: y - 200, behavior: 'instant' }), haut);
    await page.waitForFunction(() => {
        const s = document.querySelector('[data-cx-film]');
        return s && s.classList.contains('is-peint');
    }, null, { timeout: 20000 }).catch(() => {});

    await page.evaluate((y) => {
        const s = document.querySelector('[data-cx-film]');
        window.scrollTo({ top: y + s.offsetHeight * 0.32, behavior: 'instant' });
    }, haut);
    await page.waitForTimeout(1400);

    const e = await etat(page);
    console.log('\nMOBILE 390x844');
    console.log('  moteur      :', e.classes);
    console.log('  canvas      :', e.canvas);
    console.log('  legende     : «', e.legende, '»');
    console.log('  debordement horizontal :', e.debordementH ? 'OUI — DEFAUT' : 'non');
    console.log('  erreurs JS  :', erreurs.length ? erreurs.join(' | ') : 'aucune');
    await page.screenshot({ path: `${SORTIE}mobile-film.png` });

    await page.evaluate(() => {
        const b = document.getElementById('telecharger');
        if (b) b.scrollIntoView({ block: 'center', behavior: 'instant' });
    });
    await page.waitForTimeout(900);
    await page.screenshot({ path: `${SORTIE}mobile-telechargement.png` });
    await contexte.close();
}

/* ------------------------------------------------------- 3. MOUVEMENT REDUIT */
{
    const { contexte, page, erreurs } = await ouvrir({ width: 1440, height: 900, reducedMotion: 'reduce' });

    await page.evaluate(() => {
        const s = document.querySelector('[data-cx-film]');
        window.scrollTo({ top: s.getBoundingClientRect().top + window.scrollY - 100, behavior: 'instant' });
    });
    await page.waitForTimeout(4000);

    const e = await page.evaluate(() => {
        const s = document.querySelector('[data-cx-film]');
        return {
            classes: s.className,
            hauteur: s.offsetHeight,
            etapesVisibles: getComputedStyle(document.querySelector('.cx-film__etapes')).display,
            frames: performance.getEntriesByType('resource').filter((r) => r.name.includes('journey-film/')).length,
        };
    });

    console.log('\nMOUVEMENT REDUIT 1440x900');
    console.log('  classes     :', e.classes, '(doit rester « cx-film » seul)');
    console.log('  hauteur     :', e.hauteur, 'px (doit rester la liste, pas 800vh)');
    console.log('  liste       :', e.etapesVisibles);
    console.log('  frames telechargees :', e.frames, '(doit etre 0)');
    console.log('  erreurs JS  :', erreurs.length ? erreurs.join(' | ') : 'aucune');
    await page.screenshot({ path: `${SORTIE}reduced-motion.png`, fullPage: false });
    await contexte.close();
}

/* --------------------------------------------------------------- 4. LES DEUX THEMES
   La section a longtemps été sombre quel que soit le thème. Ce contrôle mesure qu'elle
   RÉPOND au thème : si les deux colonnes sont identiques, elle ne le fait plus. */
{
    const lu = {};

    for (const theme of ['light', 'dark']) {
        const { contexte, page } = await ouvrir({ width: 1440, height: 900, theme });

        // DEUX FILMS : le clair montre la mission de jour, le sombre celle de nuit.
        const variantes = new Set();
        page.on('request', (r) => {
            const m = r.url().match(/journey-film\/(jour|nuit)\//);
            if (m) variantes.add(m[1]);
        });

        await page.evaluate(() => {
            const s = document.querySelector('[data-cx-film]');
            window.scrollTo({ top: s.getBoundingClientRect().top + window.scrollY - 200, behavior: 'instant' });
        });
        await page.waitForFunction(() => {
            const s = document.querySelector('[data-cx-film]');
            return s && s.classList.contains('is-peint');
        }, null, { timeout: 20000 }).catch(() => {});

        lu[theme + ':variantes'] = [...variantes].join(',') || 'aucune';

        await page.evaluate(() => {
            const b = document.getElementById('telecharger');
            if (b) b.scrollIntoView({ block: 'center', behavior: 'instant' });
        });
        await page.waitForTimeout(900);

        lu[theme] = await page.evaluate(() => {
            const g = (sel, prop) => {
                const el = document.querySelector(sel);
                return el ? getComputedStyle(el)[prop] : null;
            };
            return {
                fond: g('[data-cx-film]', 'backgroundColor'),
                titre: g('.cx-apps__titre', 'color'),
                carte: g('.cx-apps__carte', 'backgroundColor'),
                magasin: g('.cx-apps__bouton-magasin', 'color'),
            };
        });

        await page.screenshot({ path: `${SORTIE}theme-${theme}.png` });
        await contexte.close();
    }

    console.log('\nTHEMES');
    console.log(`  film      clair=${lu['light:variantes']} (attendu jour)   sombre=${lu['dark:variantes']} (attendu nuit)`
        + (lu['light:variantes'] === 'jour' && lu['dark:variantes'] === 'nuit' ? '' : '   — MAUVAISE VARIANTE'));
    for (const cle of ['fond', 'titre', 'carte', 'magasin']) {
        const identique = lu.light[cle] === lu.dark[cle];
        console.log(`  ${cle.padEnd(9)} clair=${String(lu.light[cle]).padEnd(26)} sombre=${lu.dark[cle]}`
            + (identique ? '   — NE SUIT PAS LE THEME' : ''));
    }
}

await navigateur.close();
console.log('\nCaptures dans', SORTIE);
