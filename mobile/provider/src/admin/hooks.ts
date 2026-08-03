import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { AdminCatalog, AdminKpi } from './types';

/**
 * Les indicateurs d'accueil de la console.
 *
 * Sept compteurs, rafraîchis à la demande. Aucun rafraîchissement automatique : un administrateur
 * qui regarde son écran n'a pas besoin qu'il bouge tout seul, et une console mobile qui interroge
 * le serveur en boucle coûte de la batterie pour des chiffres qui changent à l'heure.
 */
export function useAdminOverview() {
  return useQuery<AdminKpi[]>({
    queryKey: ['admin', 'overview'],
    queryFn: async () => (await apiClient.get('/admin/overview')).data.kpis ?? [],
  });
}

/**
 * L'annuaire des modules d'administration.
 *
 * Le registre change au rythme des déploiements, pas des sessions : une heure de fraîcheur évite
 * de le retélécharger à chaque aller-retour vers l'onglet.
 */
export function useAdminCatalog() {
  return useQuery<AdminCatalog>({
    queryKey: ['admin', 'catalog'],
    queryFn: async () => {
      const { data } = await apiClient.get('/admin/catalog');

      return {
        groups: data.groups ?? [],
        counts: data.counts ?? { total: 0, covered: 0, pending: 0 },
      };
    },
    staleTime: 60 * 60 * 1000,
  });
}
