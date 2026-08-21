import fs from 'fs';
import path from 'path';
import { ecranNatifPour } from '@/screens/ModulesRoute';

/**
 * UN MODULE OUVRE SON ÉCRAN NATIF QUAND IL EN A UN.
 *
 * Mesuré avant correction : huit écrans natifs de l'espace société — planning, heures, devis,
 * consommables, recrutement, qualité et matériel, rôles et permissions, implantations, soit
 * 1 684 lignes — étaient déclarés dans `RootNavigator` et n'étaient la cible d'AUCUN appel de
 * navigation dans toute l'application. Le seul menu qui nomme ces fonctions, « Modules », ouvrait
 * la page web à la place.
 *
 * `tsc` ne peut pas voir ce défaut : une route déclarée reste valide même si rien ne l'appelle.
 * Le seul témoin possible est un contrôle des appelants — c'est ce fichier.
 */
describe('Modules → écran natif', () => {
  it('ouvre l’écran natif pour les chemins qui en ont un', () => {
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/planning')).toBe('CompanyPlanning');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/heures')).toBe('CompanyTimesheets');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/devis')).toBe('CompanyQuotes');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/recrutement')).toBe('CompanyRecruitment');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/qualite-materiel')).toBe('CompanyQualityFleet');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/roles-permissions')).toBe('CompanyRolePermissions');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/implantations')).toBe('CompanyAgencies');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/consommables')).toBe('CompanyInventory');
  });

  it('tolère une requête ou une barre finale, que le serveur peut ajouter', () => {
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/planning/')).toBe('CompanyPlanning');
    expect(ecranNatifPour('/dashboard/entreprise-prestataire/planning?embed=1')).toBe('CompanyPlanning');
  });

  it('laisse embarquer ce qui n’a pas encore d’écran natif', () => {
    /*
     * TÉMOIN POSITIF. Sans lui, on ne saurait pas si la table capture TOUT — auquel cas un module
     * sans écran natif ne s'ouvrirait plus du tout. L'approche hybride veut précisément que le
     * reste continue de passer par la WebView.
     */
    expect(ecranNatifPour('/aide')).toBeUndefined();
    expect(ecranNatifPour('/legal/cookies')).toBeUndefined();
  });

  it('ne vise pas un écran qui vit dans une AUTRE liste de routes', () => {
    /*
     * `/user/profile` avait été mappé vers `Profile` — un écran de `TabParamList`, pas de
     * `RootStackParamList`. Le viser depuis la pile racine n'aurait mené nulle part. C'est le
     * vérificateur de types qui a refusé l'entrée ; ce test garde la porte fermée.
     */
    expect(ecranNatifPour('/user/profile')).toBeUndefined();
  });

  it('toute pile qui propose « Modules » sait aussi ouvrir un module', () => {
    /*
     * `RootNavigator` monte quatre piles selon l'espace. Deux d'entre elles — admin et
     * super-admin — déclaraient `Modules` sans déclarer `EmbeddedModule` : le répertoire
     * s'ouvrait, et AUCUNE de ses vingt-quatre entrées ne faisait quoi que ce soit.
     *
     * Ce contrôle compte les déclarations plutôt que de relire les branches à la main : elles
     * bougent, le rapport entre les deux ne doit pas bouger.
     */
    const navigateur = fs.readFileSync(
      path.join(__dirname, '..', '..', 'src', 'navigation', 'RootNavigator.tsx'),
      'utf8',
    );

    const repertoires = (navigateur.match(/name="Modules"/g) ?? []).length;
    const hotes = (navigateur.match(/name="EmbeddedModule"/g) ?? []).length;

    expect(repertoires).toBeGreaterThan(0);
    expect(hotes).toBeGreaterThanOrEqual(repertoires);
  });

  it('ne nomme que des routes réellement déclarées dans le navigateur', () => {
    /*
     * Un nom de route mal orthographié, ou renommé plus tard, ferait échouer la navigation À
     * L'EXÉCUTION seulement — et l'on retomberait sur un écran injoignable, le défaut d'origine.
     */
    const navigateur = fs.readFileSync(
      path.join(__dirname, '..', '..', 'src', 'navigation', 'RootNavigator.tsx'),
      'utf8',
    );

    const chemins = [
      '/dashboard/entreprise-prestataire/planning',
      '/dashboard/entreprise-prestataire/dispatch',
      '/dashboard/entreprise-prestataire/taches',
      '/dashboard/entreprise-prestataire/consommables',
      '/dashboard/entreprise-prestataire/heures',
      '/dashboard/entreprise-prestataire/devis',
      '/dashboard/entreprise-prestataire/sites',
      '/dashboard/entreprise-prestataire/roles-permissions',
      '/dashboard/entreprise-prestataire/equipes-terrain',
      '/dashboard/entreprise-prestataire/implantations',
      '/dashboard/entreprise-prestataire/equipe',
      '/dashboard/entreprise-prestataire/recrutement',
      '/dashboard/entreprise-prestataire/canaux',
      '/dashboard/entreprise-prestataire/qualite-materiel',
      '/notifications',
      '/provider/onboarding',
    ];

    const manquantes = chemins
      .map(c => ecranNatifPour(c))
      .filter((nom): nom is NonNullable<typeof nom> => Boolean(nom))
      .filter(nom => ! navigateur.includes(`name="${nom}"`));

    expect(manquantes).toEqual([]);
  });
});
