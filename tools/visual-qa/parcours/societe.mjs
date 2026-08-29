/* LES DEUX SOCIETES — locaux, membres, repartition des missions. */
import { EchecDEtape, attendreLeTexte, cliquer, contexteConnecte, jeton, ouvrir, remplir } from './socle.mjs';

/** La societe cliente enregistre un local, et le retrouve dans sa liste. */
export async function creerUnLocal(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'entreprise');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/entreprise-client/locaux', 'ouverture de « Mes locaux »');

    await cliquer(page, ['button[wire\\:click="openCreate"]', 'button:has-text("Ajouter")'], 'ouverture du formulaire');

    const nom = `QA Local ${jeton()}`;

    await remplir(page, '#name', nom, 'saisie du nom');
    await remplir(page, '#address', 'Rue de la Loi 16', 'saisie de l’adresse');
    await remplir(page, '#postalCode', '1000', 'saisie du code postal');
    await remplir(page, '#city', 'Bruxelles', 'saisie de la ville');

    await cliquer(page, ['button[wire\\:click="saveSite"]'], 'enregistrement');
    await page.waitForTimeout(1800);

    await attendreLeTexte(page, nom, 'relecture de la liste');

    return `local « ${nom} » enregistré et visible`;
  } finally {
    await contexte.close();
  }
}

/** La societe cliente invite un membre : l'invitation apparait dans sa liste. */
export async function inviterUnMembre(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'entreprise');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/entreprise-client/membres', 'ouverture de « Membres »');

    // Le formulaire vit derriere un bouton : il n'est pas rendu tant qu'on ne l'ouvre pas.
    await cliquer(page, ['button:has-text("Inviter un membre")'], 'ouverture du formulaire');

    const courriel = `qa-membre-${jeton()}@brio.test`;

    await remplir(page, '#inviteEmail', courriel, 'saisie du courriel');

    // Le role se choisit par une pastille radio ; sans choix, l'invitation est refusee.
    const roles = await page.$$('input[type="radio"][wire\\:model="inviteRole"]');
    if (roles.length) {
      await roles[0].click({ force: true });
      await page.waitForTimeout(400);
    }

    await cliquer(page, ['button[wire\\:click="invite"]', 'button:has-text("Inviter")'], 'envoi de l’invitation');
    await page.waitForTimeout(2000);

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!contenu.includes(courriel)) {
      const alerte = await page.evaluate(() => {
        const n = [...document.querySelectorAll('[role="alert"], .text-red-600, .brio-alerte')];

        return n.map((e) => e.innerText.trim()).filter(Boolean).slice(0, 2).join(' · ');
      });

      throw new EchecDEtape('relecture de la liste', `« ${courriel} » absent après l’envoi — ${alerte || 'aucun message'}`);
    }

    return `invitation envoyée à ${courriel}`;
  } finally {
    await contexte.close();
  }
}

/** Le centre de repartition de la societe prestataire s'ouvre et sait dire ce qu'il a. */
export async function centreDeRepartition(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'provider_company');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/entreprise-prestataire/dispatch', 'ouverture du dispatch');

    const contenu = await page.evaluate(() => document.body.innerText);

    // Soit des missions a repartir, soit un etat vide qui le dit.
    if (!/mission|aucune|répart|dispatch/i.test(contenu)) {
      throw new EchecDEtape('lecture du centre', 'la page ne montre ni mission ni état vide');
    }

    // Les commandes d'assignation existent-elles quand il y a de quoi assigner ?
    const boutons = await page.$$('button[wire\\:click^="confirmAssign"], button[wire\\:click^="autoAssignerTout"]');

    return boutons.length
      ? `${boutons.length} commande(s) d’assignation disponibles`
      : 'aucune mission à répartir, état vide affiché';
  } finally {
    await contexte.close();
  }
}

/** L'equipe de la societe prestataire se lit, avec ses membres. */
export async function equipeDeLaSociete(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'provider_company');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/entreprise-prestataire/equipe', 'ouverture de l’équipe');

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!/équipe|membre|collaborateur|aucun/i.test(contenu)) {
      throw new EchecDEtape('lecture de l’équipe', 'la page ne parle ni de membres ni d’absence');
    }

    return 'l’équipe répond';
  } finally {
    await contexte.close();
  }
}

/** Les gros chantiers : le client groupe plusieurs metiers en une demande. */
export async function chantiersGroupes(navigateur) {
  const contexte = await contexteConnecte(navigateur, 'client');
  const page = await contexte.newPage();

  try {
    await ouvrir(page, '/dashboard/client/chantiers-groupes', 'ouverture des chantiers groupés');

    const contenu = await page.evaluate(() => document.body.innerText);

    if (!/chantier|lot|métier|aucun/i.test(contenu)) {
      throw new EchecDEtape('lecture de la page', 'la page ne parle ni de chantiers ni d’absence');
    }

    return 'les chantiers groupés répondent';
  } finally {
    await contexte.close();
  }
}
