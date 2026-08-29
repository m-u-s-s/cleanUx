/* LE MOTEUR DE COMMANDE — du besoin au prix, sans compte puis avec. */
import { EchecDEtape, cliquer, contexteConnecte, ouvrir } from './socle.mjs';

/**
 * Le parcours va jusqu'au PRIX, et s'arrete la.
 *
 * Aller au bout demanderait une carte bancaire : la plateforme n'a pas de cle Stripe reelle
 * en local, et semer de faux paiements salirait la comptabilite pour rien. Le prix est le
 * dernier point ou le moteur a tout calcule sans rien encaisser.
 */
async function jusquAuPrix(page) {
  // 1. L'intention, quand elle est demandee.
  const carte = await page.$('[data-test="mode-card-scheduled"], [data-test="mode-card-all"]');
  if (carte) {
    await carte.click();
    await page.waitForTimeout(900);
  }

  // 2. Le domaine.
  const secteurs = await page.$$('button[wire\\:click^="selectSector"]');
  if (secteurs.length === 0) {
    throw new EchecDEtape('choix du domaine', 'aucun secteur proposé — le catalogue est-il ouvert ?');
  }

  await secteurs[0].click();
  await page.waitForTimeout(1200);

  // 3. Le metier.
  const metiers = await page.$$('button[data-dock-item]');
  if (metiers.length === 0) {
    const apercu = await page.evaluate(() => document.body.innerText.slice(0, 200).replace(/\s+/g, ' '));

    throw new EchecDEtape('choix du métier', `aucun métier proposé. La page dit : « ${apercu} »`);
  }

  await metiers[0].click();
  await page.waitForTimeout(1500);

  // 4. Le prix, en centimes — jamais le texte formate.
  await page.waitForFunction(() => document.querySelector('[data-cx-price]') !== null, { timeout: 12000 })
    .catch(() => {
      throw new EchecDEtape('estimation du prix', 'aucun `data-cx-price` après le choix du métier');
    });

  const cents = await page.evaluate(() => {
    const el = document.querySelector('[data-cx-price]');

    return el ? parseInt(el.getAttribute('data-cx-price'), 10) : null;
  });

  if (!cents || cents <= 0) {
    throw new EchecDEtape('estimation du prix', `prix nul ou absent (${cents})`);
  }

  return cents;
}

/** Un visiteur non connecte obtient un prix : c'est la promesse « prix avant identite ». */
export async function estimationSansCompte(navigateur) {
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/commander', 'ouverture du moteur');

    const cents = await jusquAuPrix(page);

    return `prix estimé sans compte : ${(cents / 100).toFixed(2)} €`;
  } finally {
    await contexte.close();
  }
}

/** Un client connecte parcourt le meme moteur depuis son espace. */
export async function estimationClientConnecte(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'client');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/client/rendez-vous/nouveau', 'ouverture depuis l’espace client');

    const cents = await jusquAuPrix(page);

    return `prix estimé : ${(cents / 100).toFixed(2)} €`;
  } finally {
    await contexte.close();
  }
}

/** La liste des rendez-vous du client s'ouvre et sait dire qu'elle est vide. */
export async function listeDesRendezVous(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'client');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/client/rendez-vous', 'ouverture de la liste');

    const contenu = await page.evaluate(() => document.body.innerText);

    // Soit des rendez-vous, soit un etat vide qui le DIT. Une page qui ne dit ni l'un ni
    // l'autre laisse le client devant un blanc.
    const parle = /rendez-vous|aucun|réserv/i.test(contenu);

    if (!parle) {
      throw new EchecDEtape('lecture de la liste', 'la page ne montre ni rendez-vous ni état vide');
    }

    return 'la liste répond';
  } finally {
    await contexte.close();
  }
}
