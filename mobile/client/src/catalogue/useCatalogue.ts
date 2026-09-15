import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { useTraduction } from '@/i18n';
import type { ModeCatalogue, ReponseCatalogue } from './types';

/**
 * LE CATALOGUE NE SE DÉCIDE PAS ICI.
 *
 * Le serveur applique le filtre du moteur de commande (mode, zone du panier ouvert ou du lieu par
 * défaut) : une liste tenue en dur dans l'application proposerait des métiers que la commande
 * refuserait ensuite.
 */
export function useCatalogue(mode: ModeCatalogue) {
  // Les libellés arrivent traduits par le serveur : sans la langue dans la clé, une réponse gardée
  // en cache resterait affichée dans l'ancienne langue. Et la langue VOYAGE avec la requête :
  // `choisirLaLangue` prévient l'écran avant d'enregistrer la langue sur le compte, et le serveur,
  // qui ne lirait que le compte, répondrait encore dans l'ancienne.
  const { langue } = useTraduction();

  return useQuery<ReponseCatalogue>({
    queryKey: ['catalogue', mode, langue],
    queryFn: async () => {
      const { data } = await apiClient.get<ReponseCatalogue>('/client/catalogue', { params: { mode, lang: langue } });

      return data;
    },
    // Le catalogue change au rythme de l'administration, pas de la session.
    staleTime: 5 * 60 * 1000,
  });
}
