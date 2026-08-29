/* INSCRIPTION ET CONNEXION — la porte d'entree de la plateforme. */
import {
  BASE, EchecDEtape, attendreLeTexte, cliquer, contexteConnecte, jeton, ouvrir, remplir,
} from './socle.mjs';

/** Un particulier s'inscrit, puis arrive dans son espace. */
export async function inscriptionClientParticulier(navigateur) {
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/register', 'ouverture du formulaire');

    // Le type de compte est un bouton radio dessine : on clique la carte.
    await cliquer(page, ['button[role="radio"]:has-text("Client particulier")'], 'choix du profil');

    const marque = jeton();
    const courriel = `qa-inscription-${marque}@brio.test`;

    await remplir(page, '#name', `QA Inscription ${marque}`, 'saisie du nom');
    await remplir(page, '#email', courriel, 'saisie du courriel');
    await remplir(page, '#password', 'MotDePasseQA!2026', 'saisie du mot de passe');
    await remplir(page, '#password_confirmation', 'MotDePasseQA!2026', 'confirmation du mot de passe');

    const cgu = await page.$('#terms');
    if (cgu) await cgu.check();

    await Promise.all([
      page.waitForURL((u) => !u.pathname.endsWith('/register'), { timeout: 45000 }).catch(() => {}),
      cliquer(page, ['button[type="submit"]'], 'envoi du formulaire'),
    ]);

    const arrivee = new URL(page.url()).pathname;

    if (arrivee.endsWith('/register')) {
      const erreurs = await page.evaluate(() => {
        const n = [...document.querySelectorAll('.text-red-600, .text-red-500, [role="alert"]')];

        return n.map((e) => e.innerText.trim()).filter(Boolean).slice(0, 3).join(' · ');
      });

      throw new EchecDEtape('envoi du formulaire', `resté sur /register — ${erreurs || 'aucun message affiché'}`);
    }

    return `compte créé, arrivée sur ${arrivee}`;
  } finally {
    await contexte.close();
  }
}

/** Une societe prestataire s'inscrit : le formulaire lui demande d'autres champs. */
export async function inscriptionSocietePrestataire(navigateur) {
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/register', 'ouverture du formulaire');
    await cliquer(page, ['button[role="radio"]:has-text("Société de services")'], 'choix du profil');

    const marque = jeton();

    await remplir(page, '#name', `QA Société ${marque}`, 'saisie du nom');
    await remplir(page, '#email', `qa-societe-${marque}@brio.test`, 'saisie du courriel');

    const raison = await page.$('#provider_company_name');
    if (raison) await raison.fill(`QA Services ${marque}`);

    await remplir(page, '#password', 'MotDePasseQA!2026', 'saisie du mot de passe');
    await remplir(page, '#password_confirmation', 'MotDePasseQA!2026', 'confirmation du mot de passe');

    const cgu = await page.$('#terms');
    if (cgu) await cgu.check();

    await Promise.all([
      page.waitForURL((u) => !u.pathname.endsWith('/register'), { timeout: 45000 }).catch(() => {}),
      cliquer(page, ['button[type="submit"]'], 'envoi du formulaire'),
    ]);

    const arrivee = new URL(page.url()).pathname;

    if (arrivee.endsWith('/register')) {
      const erreurs = await page.evaluate(() => {
        const n = [...document.querySelectorAll('.text-red-600, .text-red-500, [role="alert"]')];

        return n.map((e) => e.innerText.trim()).filter(Boolean).slice(0, 3).join(' · ');
      });

      throw new EchecDEtape('envoi du formulaire', `resté sur /register — ${erreurs || 'aucun message affiché'}`);
    }

    return `société créée, arrivée sur ${arrivee}`;
  } finally {
    await contexte.close();
  }
}

/** Un mauvais mot de passe est REFUSE — le temoin de la connexion. */
export async function connexionRefuseeSiMauvaisMotDePasse(navigateur) {
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/login', 'ouverture de la connexion');
    await remplir(page, 'input[name="email"]', 'lemoine.gabrielle@example.net', 'saisie du courriel');
    await remplir(page, 'input[name="password"]', 'ce-mot-de-passe-est-faux', 'saisie du mot de passe');

    await cliquer(page, ['button[type="submit"]'], 'envoi');
    await page.waitForTimeout(1500);

    const chemin = new URL(page.url()).pathname;

    if (!chemin.endsWith('/login')) {
      throw new EchecDEtape('vérification du refus', `un mauvais mot de passe a ouvert ${chemin}`);
    }

    return 'refusé, comme attendu';
  } finally {
    await contexte.close();
  }
}

/** Chaque role se connecte et atterrit dans SON espace. */
export async function connexionDeChaqueRole(navigateur) {
  const attendus = {
    client: '/dashboard/client',
    entreprise: '/dashboard/entreprise-client',
    provider: '/dashboard/employe',
    provider_company: '/dashboard/entreprise-prestataire',
    admin: '/admin',
  };

  const ecarts = [];

  for (const [cred, prefixe] of Object.entries(attendus)) {
    const contexte = await contexteConnecte(navigateur, cred);
    const page = await contexte.newPage();

    await page.goto(BASE + '/dashboard', { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(900);

    const arrivee = new URL(page.url()).pathname;

    if (!arrivee.startsWith(prefixe)) {
      ecarts.push(`${cred} → ${arrivee} (attendu ${prefixe}…)`);
    }

    await contexte.close();
  }

  if (ecarts.length) {
    throw new EchecDEtape('atterrissage', ecarts.join(' | '));
  }

  return 'les cinq rôles atterrissent dans leur espace';
}
