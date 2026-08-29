/*
 * LE TOUR DES PARCOURS.
 *
 * Le balayage des pages dit qu'elles s'ouvrent ; celui-ci dit qu'on peut S'EN SERVIR. Chaque
 * parcours nomme l'etape ou il tombe — « échec » sans dire où oblige a tout rejouer a la main.
 *
 *   node parcours/index.mjs                    tout
 *   node parcours/index.mjs entree reservation  seulement ces familles
 */
import { chromium } from 'playwright';
import { jouer } from './socle.mjs';
import * as entree from './entree.mjs';
import * as reservation from './reservation.mjs';
import * as societe from './societe.mjs';
import * as transverses from './transverses.mjs';

const FAMILLES = {
  entree: [
    ['inscription — client particulier', entree.inscriptionClientParticulier],
    ['inscription — société prestataire', entree.inscriptionSocietePrestataire],
    ['connexion — mot de passe faux refusé', entree.connexionRefuseeSiMauvaisMotDePasse],
    ['connexion — chaque rôle atterrit chez lui', entree.connexionDeChaqueRole],
  ],
  reservation: [
    ['estimation — sans compte', reservation.estimationSansCompte],
    ['estimation — client connecté', reservation.estimationClientConnecte],
    ['liste des rendez-vous', reservation.listeDesRendezVous],
  ],
  societe: [
    ['société cliente — créer un local', societe.creerUnLocal],
    ['société cliente — inviter un membre', societe.inviterUnMembre],
    ['société prestataire — centre de répartition', societe.centreDeRepartition],
    ['société prestataire — équipe', societe.equipeDeLaSociete],
    ['client — chantiers groupés', societe.chantiersGroupes],
  ],
  transverses: [
    ['prestataire — ses missions', transverses.missionsDuPrestataire],
    ['prestataire — sa journée', transverses.journeeDuPrestataire],
    ['client — messagerie', transverses.messagerieDuClient],
    ['client — litiges', transverses.litigesDuClient],
    ['prestataire — litiges', transverses.litigesDuPrestataire],
    ['société — litiges', transverses.litigesDeLaSociete],
    ['prestataire — badges', transverses.badgesDuPrestataire],
    ['client — fidélité', transverses.fideliteDuClient],
    ['client — parrainage donne un code', transverses.parrainageDonneUnCode],
    ['admin — codes de réduction', transverses.creerUnCodeDeReduction],
    ['client — historique des missions', transverses.suiviDeMission],
  ],
};

const run = async () => {
  const demandees = process.argv.slice(2);
  const familles = Object.keys(FAMILLES).filter((f) => demandees.length === 0 || demandees.includes(f));

  const navigateur = await chromium.launch();
  const verdicts = [];

  for (const famille of familles) {
    console.log(`\n=== ${famille} ===`);

    for (const [nom, fonction] of FAMILLES[famille]) {
      const v = await jouer(nom, () => fonction(navigateur));
      verdicts.push({ famille, ...v });

      console.log(v.ok
        ? `  ✓ ${nom}${v.detail ? ' — ' + v.detail : ''}`
        : `  ✗ ${nom}\n      étape « ${v.etape} » : ${v.detail}`);
    }
  }

  await navigateur.close();

  const echecs = verdicts.filter((v) => !v.ok);
  console.log(`\n=== ${verdicts.length - echecs.length}/${verdicts.length} parcours passent ===`);

  process.exit(echecs.length ? 1 : 0);
};

run();
