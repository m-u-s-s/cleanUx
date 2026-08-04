/**
 * La descente géographique du catalogue, côté mobile.
 *
 * CE QUE CE FICHIER PROTÈGE EN PREMIER, c'est la JOIGNABILITÉ. Ce projet a déjà livré des écrans
 * corrects que rien n'atteignait : `tsc` et Jest ne disent rien d'un composant qu'aucune route ne
 * monte. Les deux niveaux profonds vivent sur la pile racine et l'onglet dans la barre — trois
 * fichiers qui doivent s'accorder, et aucun compilateur ne le vérifie.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..');

const lire = (relatif: string) => fs.readFileSync(path.join(RACINE, relatif), 'utf8');

/**
 * Le même fichier, espaces normalisés.
 *
 * Le JSX coupe les phrases aux retours à la ligne : chercher une phrase telle qu'elle s'affiche
 * échouerait sur une mise en forme, pas sur un manque. On compare donc le texte rendu, pas sa
 * disposition dans le fichier.
 */
const lireAplati = (relatif: string) => lire(relatif).replace(/\s+/g, ' ');

describe('catalogue mobile — joignabilité', () => {
  it('l’onglet Catalogue est dans la barre admin', () => {
    const navigateur = lire('src/admin/AdminNavigator.tsx');

    // Sans cet onglet, l'écran des pays n'existe que dans les tests.
    expect(navigateur).toContain('name="AdminCatalog"');
    expect(navigateur).toContain('CatalogCountriesScreen');
  });

  it('les deux niveaux profonds sont montés sur la pile racine', () => {
    const racine = lire('src/navigation/RootNavigator.tsx');

    /*
     * Une route inconnue fait tomber la navigation À L'OUVERTURE, pas à la compilation : c'est
     * l'utilisateur qui découvre l'erreur, sur un écran blanc.
     */
    expect(racine).toContain('name="AdminCatalogZones"');
    expect(racine).toContain('name="AdminCatalogTrades"');
    expect(racine).toContain('CatalogZonesScreen');
    expect(racine).toContain('CatalogZoneTradesScreen');
  });

  it('les routes profondes sont déclarées dans le typage de la pile', () => {
    const types = lire('src/navigation/types.ts');

    expect(types).toContain('AdminCatalogZones:');
    expect(types).toContain('AdminCatalogTrades:');
  });

  it('l’onglet est déclaré dans le typage des onglets admin', () => {
    expect(lire('src/admin/types.ts')).toContain('AdminCatalog: undefined;');
  });

  it('chaque écran navigue vers une route qui existe', () => {
    const racine = lire('src/navigation/RootNavigator.tsx');

    const cibles = [
      ['src/admin/catalogue/CatalogCountriesScreen.tsx', 'AdminCatalogZones'],
      ['src/admin/catalogue/CatalogZonesScreen.tsx', 'AdminCatalogTrades'],
    ] as const;

    for (const [fichier, route] of cibles) {
      // L'écran appelle la route…
      expect(lire(fichier)).toContain(`navigate('${route}'`);
      // …et la pile la monte. Les deux moitiés, sinon le test ne prouve rien.
      expect(racine).toContain(`name="${route}"`);
    }
  });
});

describe('catalogue mobile — ce que les écrans promettent', () => {
  it('l’écran des métiers avertit qu’il n’est pas encore branché', () => {
    const ecran = lireAplati('src/admin/catalogue/CatalogZoneTradesScreen.tsx');

    // Même exigence que sur le web : un écran exact mais pas branché doit le dire, sinon on croit
    // la fonctionnalité acquise. C'est le mode d'échec le plus probable, et il est silencieux.
    expect(ecran).toContain('n’a pas encore d’effet sur ce que voit un client');
  });

  it('le cloisonnement par pays passe par le filtre serveur', () => {
    const ecran = lire('src/admin/catalogue/CatalogZonesScreen.tsx');

    // Filtrer à l'affichage laisserait passer les actions, et l'erreur n'aurait l'air de rien
    // tant qu'il n'y a qu'un seul pays en base.
    expect(ecran).toContain("filters: { country_id:");
  });

  it('l’écran des pays ne propose ni création ni suppression', () => {
    const ecran = lire('src/admin/catalogue/CatalogCountriesScreen.tsx');

    /*
     * Décision assumée : ouvrir ou fermer un marché engage la facturation et la conformité, et se
     * fait sur le web où l'écran montre ses conséquences. Un bouton « Supprimer » sur un téléphone,
     * à côté du pouce, serait un accident qui attend son heure.
     */
    expect(ecran).not.toContain('Supprimer');
    expect(ecran).not.toContain('Ajouter un pays');
  });
});

describe('catalogue mobile — les erreurs disent ce qui s’est passé', () => {
  const { messageDErreur } = require('@/admin/catalogue/erreur');
  const { ApiError } = require('@/api');

  it('traduit un code que l’on sait expliquer', () => {
    /*
     * LE CAS EXACT QUI A COÛTÉ UN ALLER-RETOUR. L'écran affichait « Impossible de charger les
     * pays » alors que le serveur répondait `invalid_sort` avec la liste des tris permis.
     */
    expect(messageDErreur(new ApiError(422, 'invalid_sort', 'x'), 'Défaut.')).toContain('Tri non pris en charge');
  });

  it('montre le code brut quand il n’a pas de traduction', () => {
    // Un code inconnu vaut mieux qu'une phrase rassurante : il se cherche, elle non.
    expect(messageDErreur(new ApiError(500, 'boom_inattendu', 'x'), 'Défaut.')).toContain('boom_inattendu');
  });

  it('retombe sur le message par défaut hors ApiError', () => {
    expect(messageDErreur(new Error('réseau'), 'Défaut.')).toBe('Défaut.');
    expect(messageDErreur(null, 'Défaut.')).toBe('Défaut.');
  });

  it('les trois écrans emploient le traducteur', () => {
    for (const ecran of [
      'src/admin/catalogue/CatalogCountriesScreen.tsx',
      'src/admin/catalogue/CatalogZonesScreen.tsx',
      'src/admin/catalogue/CatalogZoneTradesScreen.tsx',
    ]) {
      expect(lire(ecran)).toContain('messageDErreur(error');
    }
  });
});
