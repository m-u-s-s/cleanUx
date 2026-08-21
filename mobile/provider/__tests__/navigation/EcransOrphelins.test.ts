import fs from 'node:fs';
import path from 'node:path';

/**
 * UN ÉCRAN QUE PERSONNE NE PEUT OUVRIR N'EXISTE PAS.
 *
 * Ce dépôt l'a payé trois fois. `AssignmentOfferScreen`, complet, avec son compte à rebours, monté
 * nulle part pendant des mois. `TrackingScreen`, seul endroit où le GPS émettait, atteignable par un
 * bouton que personne ne pressait. Et `MissionExecutionScreen`, où un lien « Renvoyer le SMS » a été
 * posé, testé, validé par `tsc` — pour un écran qui n'est enregistré dans AUCUN navigateur, ni même
 * déclaré dans les types de route.
 *
 * NI `tsc` NI JEST NE VOIENT CELA. Un composant exporté compile ; un test qui le monte directement
 * passe. Ce qu'aucun des deux ne demande, c'est « un utilisateur peut-il y arriver ? » — et c'est la
 * seule question qui compte pour la personne devant l'écran.
 *
 * CE FICHIER LIT LA SOURCE, faute de mieux : monter le navigateur entier exigerait tout le contexte
 * natif. Il cherche les écrans que RIEN ne référence hors d'eux-mêmes, et exige une justification
 * écrite pour les exceptions.
 *
 * IL NE PROUVE PAS LA JOIGNABILITÉ, et il faut le dire : un écran référencé par un autre écran
 * lui-même orphelin passerait au travers. Ce qu'il attrape, c'est le cas qui s'est produit trois
 * fois ici — du code écrit, testé, compilé, que rien au monde n'appelle.
 */
const RACINE = path.resolve(__dirname, '../../src');
const APP = path.resolve(__dirname, '../../App.tsx');

/**
 * Écrans délibérément sans point d'entrée, avec la raison. Toute autre absence est un défaut, et
 * cette liste n'est pas une permission durable : chaque entrée est une dette.
 */
const SANS_ENTREE: Record<string, string> = {
  /*
   * `MissionExecutionScreen.tsx` a occupé cette liste et a été SUPPRIMÉ le 2026-08-16, la
   * condition posée ici étant remplie : `MissionFieldScreen` porte désormais le compteur, et
   * l'ancien écran en tenait un faux — un chronomètre parti de zéro à chaque montage, qui
   * repartait à zéro dès qu'on quittait l'écran. Le laisser dormant, c'était garantir qu'on le
   * recopie le jour où il faudrait afficher un temps écoulé quelque part.
   */
  /*
   * `MoreScreen.tsx` a occupé cette liste et a été SUPPRIMÉ le 2026-08-21, avec `HomeScreen`,
   * `SettingsScreen` et `EarningsScreen`. La comparaison exigée avant toute suppression a été
   * faite écran par écran : les trois premiers n'apportaient RIEN que leur équivalent routé
   * n'offrait déjà — le menu de `MoreScreen` est inclus dans `ProfileScreen`, l'unique appel de
   * `SettingsScreen` (`PUT /provider/profile`) est exactement celui de `LanguageScreen`, et les
   * compteurs de `HomeScreen` vivent dans la feuille d'actions du tableau de bord.
   *
   * `EarningsScreen` est un cas à part, et instructif : `WalletScreen` l'a remplacé dans l'onglet
   * « Revenus » parce qu'il sait RETIRER. Mais il ne savait pas ÉCHOUER — pas d'état d'erreur, pas
   * de reprise, un « 0.00 EUR » affiché quand l'API refusait. Sa gestion d'erreur a donc été
   * portée AVANT la suppression, et les cinq tests de chemins d'échec suivent le portefeuille.
   * Une comparaison qui ne regarde que les hooks et les endpoints ne voit pas cela.
   */
};

/**
 * CE QU'ON CHERCHE : un écran que RIEN ne référence hors de lui-même.
 *
 * Le critère n'est pas « enregistré dans un navigateur » — beaucoup d'écrans légitimes sont rendus
 * par un autre écran plutôt que poussés comme route. Ce qui condamne un fichier, c'est qu'aucun
 * autre ne le nomme : ni pile, ni écran parent, ni point d'entrée de l'application.
 */
function fichiersDEcran(dossier: string): string[] {
  return fs.readdirSync(dossier).filter((f) => f.endsWith('Screen.tsx'));
}

function toutLeCode(): Map<string, string> {
  const fichiers = new Map<string, string>();

  const parcourir = (dossier: string) => {
    for (const entree of fs.readdirSync(dossier, { withFileTypes: true })) {
      const complet = path.join(dossier, entree.name);

      if (entree.isDirectory()) {
        parcourir(complet);
      } else if (entree.name.endsWith('.ts') || entree.name.endsWith('.tsx')) {
        fichiers.set(complet, fs.readFileSync(complet, 'utf8'));
      }
    }
  };

  parcourir(RACINE);
  fichiers.set(APP, fs.readFileSync(APP, 'utf8'));

  return fichiers;
}

describe('Écrans joignables', () => {
  const dossierEcrans = path.join(RACINE, 'screens');

  it('aucun écran n’est ignoré de tout le reste du code', () => {
    const code = toutLeCode();

    const orphelins = fichiersDEcran(dossierEcrans).filter((fichier) => {
      if (fichier in SANS_ENTREE) {
        return false;
      }

      const composant = fichier.replace('.tsx', '');
      const propre = path.join(dossierEcrans, fichier);

      for (const [chemin, source] of code) {
        if (chemin !== propre && source.includes(composant)) {
          return false;
        }
      }

      return true;
    });

    expect(orphelins).toEqual([]);
  });

  it('les dettes déclarées existent encore', () => {
    const presents = fichiersDEcran(dossierEcrans);

    // Une exception qui ne correspond plus à aucun fichier est une dette éteinte que la liste
    // continue de porter : elle finirait par couvrir un écran homonyme ajouté plus tard.
    const perimees = Object.keys(SANS_ENTREE).filter((f) => !presents.includes(f));

    expect(perimees).toEqual([]);
  });
});
