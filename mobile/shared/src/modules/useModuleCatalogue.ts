import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

/**
 * LE CATALOGUE DES MODULES, LU DEPUIS LE SERVEUR.
 *
 * POURQUOI PAS UNE LISTE EN DUR ICI. Les applications portent déjà `config/parity.php` côté
 * serveur, une seconde liste de modules tenue à côté de `config/modules.php`. En écrire une
 * troisième, en TypeScript, serait la troisième occasion d'en oublier une — et ce dépôt a déjà
 * payé le prix d'une table dupliquée : deux copies du même hook, donc deux fois le même défaut.
 *
 * LE CONTEXTE N'EST PAS UN PARAMÈTRE. Le serveur le déduit du rôle porté par le jeton. L'envoyer
 * depuis le client permettrait à un compte de réclamer la liste des modules d'administration : les
 * cases ne s'ouvriraient pas, mais la liste elle-même renseigne sur la plateforme.
 */
export interface ModuleDuCatalogue {
  key: string;
  label: string;
  /** Emoji — le mobile l'affiche tel quel, il n'a pas la table Heroicon du web. */
  icon: string;
  /** Chemin web, ouvert dans l'hôte WebView partagé. */
  path: string;
}

export interface GroupeDeModules {
  category: string;
  label: string;
  modules: ModuleDuCatalogue[];
}

export interface CatalogueDeModules {
  context: string;
  groups: GroupeDeModules[];
}

export function useModuleCatalogue(enabled = true) {
  return useQuery<CatalogueDeModules>({
    queryKey: ['module-catalogue'],
    enabled,
    queryFn: async () => {
      const { data } = await apiClient.get<CatalogueDeModules>('/modules');

      return data;
    },
    /*
     * Le catalogue ne change qu'au déploiement : le relire à chaque ouverture de l'écran ferait
     * payer un aller-retour réseau pour une liste qui n'a pas bougé. Cinq minutes suffisent à
     * refléter un module activé sans que l'utilisateur ait à redémarrer.
     */
    staleTime: 5 * 60 * 1000,
  });
}
