/* LES MODULES TRANSVERSES — missions, messages, litiges, badges, promotions, parrainage. */
import { EchecDEtape, cliquer, contexteConnecte, jeton, ouvrir, remplir } from './socle.mjs';

/**
 * Un ecran « repond » quand il montre SOIT sa matiere, SOIT un etat vide qui le dit.
 *
 * Une page blanche entre les deux est le pire des cas : rien ne plante, et l'utilisateur ne
 * sait pas s'il n'a rien ou si la page est cassee.
 */
async function ecranQuiParle(navigateur, cred, chemin, motifs, nom) {
  const contexte = await contexteConnecte(navigateur, cred);
  const page = await contexte.newPage();

  try {
    await ouvrir(page, chemin, `ouverture de ${nom}`);

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!motifs.test(contenu)) {
      const apercu = contenu.slice(0, 220).replace(/\s+/g, ' ');

      throw new EchecDEtape(`lecture de ${nom}`, `ni matière ni état vide. La page dit : « ${apercu} »`);
    }

    return `${nom} répond`;
  } finally {
    await contexte.close();
  }
}

export const missionsDuPrestataire = (n) =>
  ecranQuiParle(n, 'provider', '/dashboard/employe/missions', /mission|aucune|planifi/i, 'les missions du prestataire');

export const journeeDuPrestataire = (n) =>
  ecranQuiParle(n, 'provider', '/dashboard/employe', /journée|mission|aucune|bonjour/i, 'la journée du prestataire');

export const messagerieDuClient = (n) =>
  ecranQuiParle(n, 'client', '/dashboard/client/messagerie', /message|conversation|aucun/i, 'la messagerie');

export const litigesDuClient = (n) =>
  ecranQuiParle(n, 'client', '/dashboard/client/litiges', /litige|réclamation|aucun/i, 'les litiges du client');

export const litigesDuPrestataire = (n) =>
  ecranQuiParle(n, 'provider', '/dashboard/employe/litiges', /litige|réclamation|aucun/i, 'les litiges du prestataire');

export const badgesDuPrestataire = (n) =>
  ecranQuiParle(n, 'provider', '/dashboard/employe/badges', /badge|palier|aucun/i, 'les badges');

export const fideliteDuClient = (n) =>
  ecranQuiParle(n, 'client', '/dashboard/client/fidelite', /point|fidélité|niveau|aucun/i, 'le programme de fidélité');

export const litigesDeLaSociete = (n) =>
  ecranQuiParle(n, 'entreprise', '/dashboard/entreprise-client/litiges', /litige|réclamation|aucun/i, 'les litiges de la société');

/**
 * LE PARRAINAGE DONNE UN LIEN, ET CE LIEN PORTE UN CODE.
 *
 * Chercher un mot en capitales dans la page attrapait « PROGRAMME », un titre : la mesure
 * disait vert sans rien mesurer. On lit la VALEUR du champ en lecture seule, la seule chose
 * que le filleul recevra.
 */
export async function parrainageDonneUnCode(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'client');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/client/parrainage', 'ouverture du parrainage');

    const lien = await page.evaluate(() => [...document.querySelectorAll('input[readonly]')]
      .map((i) => i.value)
      .find((v) => v && v.includes('http')) ?? null);

    if (!lien) {
      const apercu = await page.evaluate(() => document.body.innerText.slice(0, 220).replace(/\s+/g, ' '));

      throw new EchecDEtape('lecture du lien', `aucun lien d'invitation. La page dit : « ${apercu} »`);
    }

    if (!/[?/=][A-Za-z0-9_-]{4,}$/.test(lien)) {
      throw new EchecDEtape('lecture du lien', `le lien ne porte aucun code : ${lien}`);
    }

    return `lien de parrainage : ${lien}`;
  } finally {
    await contexte.close();
  }
}

/** L'administration cree un code de reduction, et le retrouve dans sa liste. */
export async function creerUnCodeDeReduction(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'admin');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/admin/promotions/codes', 'ouverture des codes de réduction');

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!/promotion|code|réduction|aucun/i.test(contenu)) {
      throw new EchecDEtape('lecture de la page', 'la page ne parle ni de codes ni d’absence');
    }

    // Le formulaire de creation vit derriere un bouton sur la plupart des ecrans admin.
    const ouvreur = page.locator('button:has-text("Nouveau"), button:has-text("Créer"), button:has-text("Ajouter")').first();

    if (await ouvreur.count()) {
      await ouvreur.click();
      await page.waitForTimeout(1400);

      const champs = await page.evaluate(() => [...document.querySelectorAll('input[id], input[wire\\:model]')].length);

      if (champs === 0) {
        throw new EchecDEtape('ouverture du formulaire', 'le bouton n’ouvre aucun champ');
      }

      return `formulaire de création ouvert (${champs} champs)`;
    }

    return 'la page des promotions répond';
  } finally {
    await contexte.close();
  }
}

/** Le suivi d'une mission cote client : la page de suivi existe et parle. */
export async function suiviDeMission(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'client');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/client/historique', 'ouverture de l’historique');

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!/mission|terminé|historique|aucun/i.test(contenu)) {
      throw new EchecDEtape('lecture de l’historique', 'la page ne parle ni de missions ni d’absence');
    }

    return 'l’historique répond';
  } finally {
    await contexte.close();
  }
}
