import fs from 'fs';
import path from 'path';

/**
 * L'ESPACE SOCIÉTÉ DOIT ÊTRE ATTEIGNABLE.
 *
 * POURQUOI CE FICHIER EXISTE. Ce dépôt a produit trois fois le même défaut, et la troisième est
 * celle qui a motivé ce lot :
 *
 *   1. `TrackingScreen` et `MissionsListScreen` — complets, testés, montés dans aucun navigateur.
 *   2. Les six modules `entreprise-client` — déclarés dans `config/parity.php`, servis par aucune
 *      surface : `ModuleHubScreen`, seule porte générique vers eux, n'est monté nulle part.
 *   3. Les cinq écrans société de l'application PRESTATAIRE — montés, testés, mais derrière une
 *      condition insatisfiable (`is_entreprise === true && organization_type ===
 *      'provider_company'`, deux termes qui s'excluent). Livrés, verts, joignables par personne.
 *
 * Dans les trois cas, `tsc` et jest étaient verts. Ils ne disent rien d'un écran que personne
 * n'atteint. Ce test lit donc les fichiers de NAVIGATION, pas les composants — et il échouera si
 * quelqu'un retire la porte sans retirer les écrans.
 */
const SRC = path.join(__dirname, '..', '..');

const lire = (rel: string) => fs.readFileSync(path.join(SRC, rel), 'utf8');

const ECRANS = [
  'CompanyOverview',
  'CompanySites',
  'CompanyBookings',
  'CompanyMembers',
  'CompanyContracts',
  'CompanyBilling',
] as const;

describe('Joignabilité de l’espace société cliente', () => {
  it.each(ECRANS)('%s est typé dans les paramètres de navigation', (ecran) => {
    expect(lire('navigation/types.ts')).toMatch(new RegExp(`${ecran}\\s*:`));
  });

  it.each(ECRANS)('%s est monté dans le navigateur racine', (ecran) => {
    const source = lire('navigation/RootNavigator.tsx');

    expect(source).toContain(`${ecran}Screen`);
    expect(source).toMatch(new RegExp(`name="${ecran}"`));
  });

  it('est un ESPACE au démarrage, pas un écran poussé sur la pile personnelle', () => {
    const racine = lire('navigation/RootNavigator.tsx');

    expect(racine).toMatch(/space === 'clientCompany'/);
    expect(racine).toContain('ClientCompanyNavigator');
    expect(racine).toContain('resolveClientSpace');
  });

  it('laisse au membre de société un chemin de RETOUR vers son espace perso', () => {
    const profil = lire('screens/ProfileScreen.tsx');

    /*
     * Sans cela, choisir « entreprise » une fois enfermerait hors de ses propres réservations —
     * l'organisation étant un rattachement du compte et non un compte distinct, la même personne
     * commande aussi pour elle-même. C'est le défaut que `clear()` a déjà corrigé deux fois dans
     * ce dépôt.
     */
    expect(profil).toContain('useClientSpacePreference');
    expect(profil).toContain('clear()');
  });

  it('le profil est monté DANS la pile société, sinon le retour serait injoignable', () => {
    expect(lire('navigation/RootNavigator.tsx')).toMatch(/name="Profile"/);
  });

  it('conditionne cette porte au SEUL drapeau qui désigne une société cliente', () => {
    const profil = lire('screens/ProfileScreen.tsx');

    expect(profil).toContain('is_entreprise');

    /*
     * LA GARDE QUI AURAIT ÉVITÉ LE DÉFAUT PRESTATAIRE.
     *
     * Là-bas, la porte exigeait `is_entreprise === true ET organization_type ===
     * 'provider_company'` : le premier terme est vrai pour une société CLIENTE, le second désigne
     * une société PRESTATAIRE. Aucun compte ne pouvait satisfaire les deux, et cinq écrans sont
     * restés invisibles depuis leur livraison sans qu'aucun test ne le voie.
     *
     * Ici, `is_entreprise` est le drapeau juste et il se suffit. Lui adjoindre un `organization_type`
     * rejouerait la même contradiction — d'où cette assertion, qui interdit la conjonction plutôt
     * que d'espérer qu'on s'en souvienne.
     */
    const porte = profil.slice(
      profil.indexOf('appartientAUneSocieteCliente'),
      profil.indexOf('appartientAUneSocieteCliente') + 400,
    );
    expect(porte).not.toContain('organization_type');
  });

  it('les cinq écrans profonds sont atteints depuis l’accueil société', () => {
    const accueil = lire('screens/company/CompanyOverviewScreen.tsx');

    for (const ecran of ECRANS.filter((e) => e !== 'CompanyOverview')) {
      expect(accueil).toContain(ecran);
    }
  });
});
