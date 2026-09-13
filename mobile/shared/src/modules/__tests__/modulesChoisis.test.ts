import { CLES_LOCATION, modulesChoisis } from '../modulesChoisis';
import type { CatalogueDeModules } from '../useModuleCatalogue';

/**
 * LE PREFIXE DE CONTEXTE EST CE QUI FAIT ECHOUER CETTE RECHERCHE.
 *
 * La premiere version cherchait des cles completes (`client:peer.owner.vehicles`). Elle marchait
 * sur un compte particulier et rendait une liste VIDE sur un compte en societe, dont les cles
 * portent `client-company:` — sans erreur et sans trace : la case disparaissait simplement.
 */
const catalogue = (contexte: string): CatalogueDeModules => ({
  context: contexte,
  groups: [
    {
      category: 'croissance',
      label: 'Croissance',
      modules: [
        { key: `${contexte}:peer.owner.vehicles`, label: 'Mes vehicules', icon: '🅿️', path: '/mes-vehicules' },
        { key: `${contexte}:peer.owner.stays`, label: 'Mes logements', icon: '🏠', path: '/mes-logements' },
      ],
    },
  ],
});

describe('modulesChoisis', () => {
  it.each(['client', 'client-company', 'employe'])(
    'trouve la meme route quel que soit le contexte (%s)',
    contexte => {
      const [module] = modulesChoisis(catalogue(contexte), CLES_LOCATION.mesVehicules);

      expect(module?.path).toBe('/mes-vehicules');
    },
  );

  it('rend les modules dans l ordre demande', () => {
    const trouves = modulesChoisis(catalogue('client'), [
      ...CLES_LOCATION.mesLogements,
      ...CLES_LOCATION.mesVehicules,
    ]);

    expect(trouves.map(m => m.path)).toEqual(['/mes-logements', '/mes-vehicules']);
  });

  /* Un compte sans le module : liste vide, pas une exception — l'appelant n'affiche pas la case. */
  it('ignore en silence une route absente du catalogue', () => {
    expect(modulesChoisis(catalogue('client'), ['peer.inexistant'])).toEqual([]);
    expect(modulesChoisis(undefined, CLES_LOCATION.mesVehicules)).toEqual([]);
  });

  /*
   * TEMOIN. Sans lui, une fonction qui rendrait TOUJOURS une liste vide passerait les deux tests
   * de refus ci-dessus.
   */
  it('temoin : le catalogue complet rend bien ses deux modules', () => {
    const tout = modulesChoisis(catalogue('client-company'), [
      ...CLES_LOCATION.mesVehicules,
      ...CLES_LOCATION.mesLogements,
    ]);

    expect(tout).toHaveLength(2);
  });
});
