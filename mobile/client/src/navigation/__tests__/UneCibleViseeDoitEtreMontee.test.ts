/**
 * UNE CIBLE VISÉE DOIT ÊTRE MONTÉE DANS L'ESPACE D'OÙ ON LA VISE.
 *
 * `RootNavigator` ne monte pas UNE pile mais une pile PAR ESPACE, et elles ne déclarent pas
 * les mêmes routes. Une route peut donc exister dans le fichier et être absente de la pile
 * réellement montée — et le fichier le dit lui-même : « une route absente ne lève rien, elle
 * ne fait simplement rien ». Rien ne plante, rien ne s'ouvre, on croit avoir mal appuyé.
 *
 * CE QUE LES OUTILS NE VOIENT PAS :
 *   - `tsc` : `keyof RootStackParamList` accepte TOUTE route du fichier, montée ou non, et
 *     `navigate(x as never)` fait taire ce qui restait de vérification.
 *   - le test de joignabilité précédent : il cherchait `name="X"` n'importe où dans le
 *     fichier — donc il passait au vert pour une route montée sur une AUTRE pile.
 *
 * CE QUE CELA COÛTAIT, mesuré dans l'espace société cliente : quatre raccourcis d'accueil
 * sur six ne faisaient rien, le répertoire de modules n'ouvrait aucun module, et le bouton
 * « Payer » d'une réservation en attente ne payait pas.
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const RACINE = join(__dirname, '..', '..');

const lire = (chemin: string): string => readFileSync(join(RACINE, chemin), 'utf8');

/** Les `name="…"` déclarés dans un fragment de source. */
const routesDeclarees = (fragment: string): Set<string> =>
  new Set([...fragment.matchAll(/name="([A-Za-z]+)"/g)].map((m) => m[1] as string));

/**
 * Le bloc d'un espace : de `if (space === 'x')` jusqu'au `if` suivant ou au `return` final.
 * On découpe plutôt que de lire le fichier entier — c'est tout l'objet de ce test.
 */
const blocDEspace = (source: string, espace: string): string => {
  const debut = source.indexOf(`if (space === '${espace}')`);

  expect(debut).toBeGreaterThan(-1);

  const suite = source.slice(debut + 1);
  const fin = suite.search(/\n  (?:if \(space ===|return \()/);

  return fin === -1 ? suite : suite.slice(0, fin);
};

/** Les cibles littérales visées par un écran : `navigate('X')` et `screen: 'X'`. */
const ciblesVisees = (source: string): Set<string> => {
  const cibles = new Set<string>();

  for (const m of source.matchAll(/\bnavigate\(\s*'([A-Za-z]+)'/g)) {
    cibles.add(m[1] as string);
  }

  // Les raccourcis passent par un tableau, puis `navigate(variable)` : le nom est ici.
  for (const m of source.matchAll(/\bscreen:\s*'([A-Za-z]+)'/g)) {
    cibles.add(m[1] as string);
  }

  return cibles;
};

describe('espace société cliente — chaque bouton mène quelque part', () => {
  const racine = lire('navigation/RootNavigator.tsx');
  const bloc = blocDEspace(racine, 'clientCompany');

  // Ce que l'espace offre vraiment : la pile, PLUS les onglets qu'elle monte.
  const montees = new Set([
    ...routesDeclarees(bloc),
    ...routesDeclarees(lire('company/ClientCompanyNavigator.tsx')),
  ]);

  /** Les écrans que cet espace affiche, et qui peuvent donc appuyer sur un bouton. */
  const ECRANS = [
    'screens/company/CompanyOverviewScreen.tsx',
    'screens/company/CompanyProfileScreen.tsx',
    'screens/company/CompanySitesScreen.tsx',
    'screens/company/CompanyBookingsScreen.tsx',
    'screens/company/CompanyBillingScreen.tsx',
    'screens/BookingDetailScreen.tsx',
    'screens/InvoiceDetailScreen.tsx',
    'screens/ModulesRoute.tsx',
  ];

  it.each(ECRANS)('%s ne vise que des routes montées ici', (chemin) => {
    const absentes = [...ciblesVisees(lire(chemin))].filter((c) => ! montees.has(c));

    expect(absentes).toEqual([]);
  });

  /*
   * TÉMOIN. Sans lui, ce fichier passerait au vert si `blocDEspace` renvoyait le fichier
   * entier — il mesurerait alors exactement le défaut qu'il existe pour interdire — ou si
   * `ciblesVisees` ne trouvait plus rien.
   */
  it('témoin : le découpage isole bien une pile, et l’extraction voit les cibles', () => {
    // La pile société ne monte PAS les écrans personnels, même s'ils sont dans le fichier.
    expect(montees.has('Referral')).toBe(false);
    expect(montees.has('Loyalty')).toBe(false);

    // …alors que le fichier entier, lui, les déclare.
    expect(racine).toContain('name="Referral"');

    // Et l'extraction voit bien ce qu'un écran vise.
    const cibles = ciblesVisees(lire('screens/BookingDetailScreen.tsx'));

    expect(cibles.size).toBeGreaterThan(3);
    expect(cibles.has('PaymentCheckout')).toBe(true);
  });
});
