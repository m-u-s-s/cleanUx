import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { ModeCatalogue, ReponseCatalogue } from './types';

/**
 * LE CATALOGUE NE SE DÉCIDE PAS ICI.
 *
 * Le serveur applique le filtre du moteur de commande (mode, zone du lieu par défaut) : une liste
 * tenue en dur dans l'application proposerait des métiers que la commande refuserait ensuite.
 */
export function useCatalogue(mode: ModeCatalogue) {
  return useQuery<ReponseCatalogue>({
    queryKey: ['catalogue', mode],
    queryFn: async () => {
      const { data } = await apiClient.get<ReponseCatalogue>('/client/catalogue', { params: { mode } });

      return data;
    },
    // Le catalogue change au rythme de l'administration, pas de la session.
    staleTime: 5 * 60 * 1000,
  });
}
