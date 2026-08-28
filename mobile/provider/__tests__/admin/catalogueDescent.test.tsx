/**
 * La descente géographique du catalogue, côté mobile.
 *
 * CE QUE CE FICHIER PROTÈGE EN PREMIER, c'est la JOIGNABILITÉ. Ce projet a déjà livré des écrans
 * corrects que rien n'atteignait : `tsc` et Jest ne disent rien d'un composant qu'aucune route ne
 * monte. Les deux niveaux profonds vivent sur la pile racine et l'onglet dans la barre — trois
 * fichiers qui doivent s'accorder, et aucun compilateur ne le vérifie.
 */
import fs from 'fs';
import { fr } from '@/i18n/catalogues/fr';
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

  it('chaque route appelée par un écran du catalogue est montée', () => {
    /*
     * PAR ÉNUMÉRATION, et non par liste écrite à la main.
     *
     * La première version nommait deux routes et les vérifiait ; elle a laissé passer un
     * `navigate('AdminTradeJourney')` ajouté ensuite vers un écran qui n'existait pas encore.
     * `tsc` ne le voit pas — l'objet de navigation est typé au plus large — et la casse ne se
     * produit qu'AU TOUCHER, sur un écran blanc.
     */
    const racine = lire('src/navigation/RootNavigator.tsx');
    const navigateur = lire('src/admin/AdminNavigator.tsx');

    const ecrans = [
      'src/admin/catalogue/CatalogCountriesScreen.tsx',
      'src/admin/catalogue/CatalogZonesScreen.tsx',
      'src/admin/catalogue/CatalogZoneTradesScreen.tsx',
    ];

    const orphelines: string[] = [];

    for (const fichier of ecrans) {
      const appels = [...lire(fichier).matchAll(/navigate\(\s*'([A-Za-z]+)'/g)].map((m) => m[1]);

      for (const route of appels) {
        const montee = racine.includes(`name="${route}"`) || navigateur.includes(`name="${route}"`);

        if (!montee) {
          orphelines.push(`${fichier} → ${route}`);
        }
      }
    }

    expect(orphelines).toEqual([]);
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

  it('l’écran des pays permet d’ajouter, modifier et supprimer', () => {
    const ecran = lire('src/admin/catalogue/CatalogCountriesScreen.tsx');

    /*
     * CE TEST DISAIT L'INVERSE, et sa prémisse était bonne à l'époque : ouvrir un marché engage la
     * facturation, et un bouton « Supprimer » à côté du pouce est un accident qui attend son heure.
     *
     * La parité mobile a été demandée explicitement. Le risque n'a pas disparu — il est traité
     * autrement : les actions vivent derrière un menu plutôt qu'alignées sous le pouce, et toute
     * action destructive passe par une confirmation.
     */
    // Le libellé vit désormais dans le catalogue : on vérifie la clé ET ce qu'elle rend.
    expect(ecran).toContain("tr('catalog_countries.ajouter_un_pays')");
    expect(fr['catalog_countries.ajouter_un_pays']).toBe('Ajouter un pays');
    expect(ecran).toContain("libelle: 'Supprimer', destructive: true");
  });

  it('toute action destructive passe par une confirmation', () => {
    const menu = lire('src/admin/catalogue/LigneActions.tsx');

    // Sur mobile il n'y a ni annulation ni Ctrl+Z, et l'écran est petit : ce qu'on efface par
    // erreur, on ne le voit même pas disparaître.
    expect(menu).toContain('Alert.alert');
    expect(menu).toContain("style: 'destructive'");
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

describe('constructeur de parcours — mobile', () => {
  it('l’écran est monté et atteignable depuis les métiers', () => {
    /*
     * Les deux moitiés. Le lot précédent avait ajouté l'appel AVANT que l'écran existe : `tsc` ne
     * l'avait pas vu, et la casse ne se serait produite qu'au toucher, sur un écran blanc.
     */
    expect(lire('src/navigation/RootNavigator.tsx')).toContain('name="AdminTradeJourney"');
    expect(lire('src/admin/catalogue/CatalogZoneTradesScreen.tsx')).toContain("navigate('AdminTradeJourney'");
    expect(lire('src/navigation/types.ts')).toContain('AdminTradeJourney:');
  });

  it('le supplément d’une réponse est éditable, et l’écran dit sa règle', () => {
    const ecran = lireAplati('src/admin/catalogue/JourneyBuilderScreen.tsx');

    // La raison d'être de cet écran : un supplément qui ne s'applique que si le client choisit
    // cette réponse. Posé sur la question, il s'appliquerait aussi à « Non ».
    expect(ecran).toContain('price_modifier_euros');
    expect(ecran).toContain('ne s’ajoute que si le client la choisit');
  });

  it('le verdict de publication est affiché', () => {
    const ecran = lire('src/admin/catalogue/JourneyBuilderScreen.tsx');

    // Régler un parcours sans savoir s'il partira, c'est découvrir le refus après coup.
    expect(ecran).toContain('can_publish');
    // Le verdict vit desormais dans le catalogue : on verifie la cle ET ce qu'elle rend.
    expect(ecran).toContain("tr('journey_builder.ce_parcours_nest_pas_encore')");
    expect(fr['journey_builder.ce_parcours_nest_pas_encore']).toContain('n’est pas encore publiable');
  });

  it('le bouton Publier est désactivé tant que le parcours ne l’est pas', () => {
    expect(lire('src/admin/catalogue/JourneyBuilderScreen.tsx')).toContain('disabled={!publiable}');
  });
});
