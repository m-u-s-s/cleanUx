import type { CatalogueDeModules, ModuleDuCatalogue } from './useModuleCatalogue';

/**
 * Retrouve des modules par leur ROUTE, dans l'ordre demandé.
 *
 * ON NE CHERCHE PAS PAR CLÉ COMPLÈTE, ET C'EST LE POINT DÉLICAT. Une clé du catalogue vaut
 * `<contexte>:<route>`, et le contexte dépend du compte : un particulier reçoit
 * `client:peer.owner.vehicles`, un compte en société `client-company:peer.owner.vehicles`, un
 * employé `employe:…`. Une liste de clés complètes marche donc pour un type de compte et rend une
 * liste vide pour les autres — sans erreur, sans trace : la case disparaît, simplement. C'est
 * exactement ce qui est arrivé à la première version, vérifiée sur un compte particulier et
 * silencieuse sur un compte en société.
 *
 * Une route absente du catalogue n'est PAS une erreur : elle signifie que ce compte n'a pas ce
 * module, et l'appelant n'affiche alors pas la case.
 */
export function modulesChoisis(
  catalogue: CatalogueDeModules | undefined,
  routes: readonly string[],
): ModuleDuCatalogue[] {
  if (!catalogue) {
    return [];
  }

  const parRoute = new Map<string, ModuleDuCatalogue>();

  for (const groupe of catalogue.groups) {
    for (const module of groupe.modules) {
      const route = module.key.slice(module.key.indexOf(':') + 1);

      if (!parRoute.has(route)) {
        parRoute.set(route, module);
      }
    }
  }

  return routes.map(r => parRoute.get(r)).filter((m): m is ModuleDuCatalogue => Boolean(m));
}

/** Les routes du module de location entre membres, telles que `config/modules.php` les nomme. */
export const CLES_LOCATION = {
  louer: ['peer.catalogue'],
  louerLogement: ['peer.sejours'],
  mesLocations: ['peer.my-rentals'],
  mesVehicules: ['peer.owner.vehicles'],
  mesLogements: ['peer.owner.stays'],
} as const;
