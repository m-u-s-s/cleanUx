import fs from 'fs';
import path from 'path';

/**
 * L'ESPACE SOCIÉTÉ PRESTATAIRE DOIT ÊTRE ATTEIGNABLE — ET DEPUIS DEUX CHEMINS.
 *
 * POURQUOI CE FICHIER EXISTE. Les cinq écrans société de cette application ont passé leur première
 * livraison entière derrière une condition insatisfiable :
 *
 *     is_entreprise === true && organization_type === 'provider_company'
 *
 * `is_entreprise` désigne une société CLIENTE ; les deux termes s'excluent. Écrans complets, API
 * complète, tests verts, joignables par personne. `CompanyScreens.test.tsx` ne l'a pas vu : il
 * monte les écrans DIRECTEMENT.
 *
 * Ce test lit donc les fichiers de navigation, pas les composants. Il garde les deux chemins,
 * parce qu'ils servent deux moments différents :
 *   - l'ESPACE, pour le gérant qui ouvre l'application pour ça ;
 *   - le PROFIL, pour le même gérant quand il travaille dans l'espace terrain.
 */
const SRC = path.join(__dirname, '..', '..', 'src');

const lire = (rel: string) => fs.readFileSync(path.join(SRC, rel), 'utf8');

const ONGLETS = [
  'CompanyOverviewTab',
  'CompanyDispatchTab',
  'CompanyFieldTeamsTab',
  'CompanyTasksTab',
  'CompanyChannelsTab',
] as const;

describe('Joignabilité de l’espace société prestataire', () => {
  it('l’aiguillage connaît l’espace', () => {
    const source = lire('admin/space.ts');

    expect(source).toContain("'providerCompany'");
    expect(source).toContain('can_manage_company');
  });

  it('le navigateur racine le monte hors de la pile terrain', () => {
    const source = lire('navigation/RootNavigator.tsx');

    expect(source).toMatch(/space === 'providerCompany'/);
    expect(source).toContain('ProviderCompanyNavigator');
  });

  it.each(ONGLETS)('%s est un onglet de l’espace', (onglet) => {
    expect(lire('company/ProviderCompanyNavigator.tsx')).toMatch(new RegExp(`name="${onglet}"`));
    expect(lire('navigation/types.ts')).toMatch(new RegExp(`${onglet}\\s*:`));
  });

  it('le sélecteur d’espace propose la société', () => {
    const source = lire('screens/SpaceSwitcherScreen.tsx');

    expect(source).toContain("onChoose('providerCompany')");
    // Conditionné : une carte offerte à un employé mènerait à des écrans de pilotage qui ne sont
    // pas les siens.
    expect(source).toContain('peutPiloterLaSociete');
  });

  it('le gérant garde un chemin de RETOUR depuis l’espace terrain', () => {
    const profil = lire('screens/ProfileScreen.tsx');

    // Sans cela, choisir « terrain » une fois enfermerait hors de l'espace société — le défaut
    // exact que `clear()` avait corrigé pour la console d'administration.
    expect(profil).toContain('plusieursEspaces');
    expect(profil).toContain('can_manage_company');
  });

  it('l’espace n’enferme pas non plus dans l’autre sens', () => {
    // Le profil est monté DANS la pile société : c'est de là que part `clear()`.
    expect(lire('navigation/RootNavigator.tsx')).toMatch(/name="Profile"/);
  });

  it('les sites desservis sont atteignables depuis les DEUX espaces', () => {
    const racine = lire('navigation/RootNavigator.tsx');

    // Montés dans la pile société ET dans la pile terrain : un gérant qui a choisi « terrain » y
    // accède par son profil, sans quoi l'écran n'existerait que pour la moitié des comptes.
    expect(racine.match(/name="CompanySites"/g)?.length).toBe(2);
    expect(lire('screens/ProfileScreen.tsx')).toContain('CompanySites');
  });

  it('les liens profonds ouvrent l’onglet demandé, pas toujours l’accueil', () => {
    const source = lire('navigation/linking.ts');

    expect(source).toContain('ProviderCompanySpace');
    expect(source).toContain("'societe'");
    expect(source).toContain("'societe/equipes'");
  });
});
