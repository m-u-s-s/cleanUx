/*
 * LE TOUR DE LA PLATEFORME.
 *
 * Ouvre CHAQUE page de chaque espace avec le compte qui a le droit d'y entrer, et rapporte
 * trois choses : le statut HTTP, les erreurs de console, et les exceptions de page. Une page
 * qui rend 200 en crachant une exception JavaScript est cassee autant qu'un 500 — la mesure
 * doit voir les deux.
 *
 *   node tour-plateforme.mjs                 tous les roles
 *   node tour-plateforme.mjs admin client    seulement ceux-la
 */
import fs from 'node:fs';
import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8000';
const SORTIE = process.env.SORTIE ?? './out/tour-plateforme.json';

const INVENTAIRE = JSON.parse(fs.readFileSync('./out/routes-par-role.json', 'utf8'));

const demandes = process.argv.slice(2);
const roles = Object.keys(INVENTAIRE).filter((r) => demandes.length === 0 || demandes.includes(r));

// Une redirection est une reponse, pas un defaut.
const ACCEPTES = new Set([200, 302]);

/*
 * LES REFUS ASSUMES, ET LEUR MOTIF.
 *
 * Un 403 n'est un defaut que si la case qui y mene est offerte. Ces trois-la sont gardes
 * ET caches au compte qui n'y a pas droit : les compter ferait du bruit permanent, et le
 * bruit permanent finit par masquer un vrai defaut. Chaque ligne dit POURQUOI.
 */
const REFUS_ASSUMES = {
  '/dashboard/client/analytics':
    'reserve aux clients SOCIETE (`isClientCompany`) ; la case est cachee au particulier',
  '/dashboard/employe/chef-equipe':
    'reserve aux chefs d equipe (`field.team.lead`) ; la case porte `isFieldTeamLead`',
  '/dashboard/entreprise-prestataire/moi/chef-equipe':
    'la meme page, sous l espace societe fusionne',
};

const run = async () => {
  const navigateur = await chromium.launch();
  const rapport = {};

  for (const role of roles) {
    const { cred, chemins } = INVENTAIRE[role];
    const contexte = await navigateur.newContext({
      viewport: { width: 1440, height: 900 },
      serviceWorkers: 'block',
    });

    if (cred) {
      await loginAs(contexte, BASE, cred);
    }

    const page = await contexte.newPage();
    const defauts = [];

    // Les erreurs de la page en cours, remises a zero avant chaque navigation.
    let console_ = [];
    let exceptions = [];

    page.on('console', (m) => {
      if (m.type() === 'error') {
        const t = m.text();
        // LE BRUIT DU POSTE, PAS DES DEFAUTS.
        //
        // Le serveur temps reel (Reverb, port 8080) n'est pas lance en local : son WebSocket
        // refuse la connexion sur TOUTE page qui ecoute un canal. Le compter ferait passer
        // une trentaine de pages saines pour cassees, et noierait les vrais defauts.
        if (/favicon|vite|\[HMR\]|net::ERR_ABORTED/i.test(t)) return;
        if (/WebSocket connection to .(ws|wss):\/\/[^']*:8080/.test(t)) return;
        if (/pusher|reverb/i.test(t) && /connection|websocket/i.test(t)) return;
        console_.push(t.slice(0, 200));
      }
    });

    page.on('pageerror', (e) => {
      const m = String(e.message);

      /*
       * LE BAC A SABLE DU HARNAIS, PAS UN DEFAUT.
       *
       * `serviceWorkers: 'block'` rend le contexte « sandboxed » : lire
       * `navigator.serviceWorker` y leve, y compris depuis du code tiers. Mesure comparee du
       * 2026-08-29 : la meme page en `serviceWorkers: 'allow'` ne leve rien. Notre propre code
       * lit desormais ce registre sous filet ; ce qui reste n'est pas a nous.
       */
      if (/Service worker is disabled because the context is sandboxed/.test(m)) return;

      exceptions.push(m.slice(0, 200));
    });

    console.log(`\n=== ${role} (${chemins.length} pages) ===`);

    for (const chemin of chemins) {
      console_ = [];
      exceptions = [];

      let statut = 0;
      let final = '';

      try {
        const r = await page.goto(BASE + chemin, { waitUntil: 'domcontentloaded', timeout: 45000 });
        statut = r ? r.status() : 0;
        await page.waitForTimeout(700);
        final = new URL(page.url()).pathname;
      } catch (e) {
        defauts.push({ chemin, statut: 0, motif: e.message.split('\n')[0].slice(0, 120) });
        continue;
      }

      const souci = [];

      const assume = REFUS_ASSUMES[chemin];

      if (statut === 403 && assume) {
        // Un refus voulu : on le dit, on ne le compte pas.
        console.log(`  · ${chemin} → 403 assumé (${assume})`);
        continue;
      }

      if (!ACCEPTES.has(statut)) souci.push(`HTTP ${statut}`);
      if (exceptions.length) souci.push(`exception JS : ${exceptions[0]}`);
      if (console_.length) souci.push(`console : ${console_[0]}`);

      if (souci.length) {
        defauts.push({ chemin, statut, final, motif: souci.join(' | ') });
        console.log(`  ✗ ${chemin} → ${souci.join(' | ')}`);
      }
    }

    rapport[role] = { total: chemins.length, defauts };
    console.log(`  ${chemins.length - defauts.length}/${chemins.length} sans défaut`);

    await contexte.close();
  }

  await navigateur.close();

  fs.writeFileSync(SORTIE, JSON.stringify(rapport, null, 2), 'utf8');

  const total = Object.values(rapport).reduce((n, r) => n + r.defauts.length, 0);
  console.log(`\n=== ${total} défaut(s) au total — détail dans ${SORTIE} ===`);

  process.exit(total ? 1 : 0);
};

run();
