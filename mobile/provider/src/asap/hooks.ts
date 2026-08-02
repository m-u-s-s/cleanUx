import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type { AsapOffer } from './types';

/**
 * Le rythme de rafraîchissement d'une course immédiate.
 *
 * Une course se joue en secondes. Rafraîchir toutes les minutes proposerait des courses déjà
 * prises, et le prestataire apprendrait à ne plus croire sa liste — puis cesserait de l'ouvrir.
 * Cinq secondes est le compromis : assez court pour rester vrai, assez long pour ne pas vider la
 * batterie d'un téléphone qui roule.
 */
export const ASAP_REFETCH_INTERVAL_MS = 5_000;

const QUERY_KEY = ['provider', 'asap-offers'] as const;

export function useAsapOffers(enabled: boolean = true) {
  const query = useQuery<AsapOffer[]>({
    queryKey: QUERY_KEY,
    queryFn: async () => (await apiClient.get('/provider/asap-offers')).data.data ?? [],
    enabled,
    refetchInterval: ASAP_REFETCH_INTERVAL_MS,
    // Une course prise ailleurs disparaît : mieux vaut une liste vide qu'une liste périmée.
    staleTime: 0,
  });

  return {
    ...query,
    offers: query.data ?? [],
    refetchIntervalMs: ASAP_REFETCH_INTERVAL_MS,
  };
}

/**
 * Prendre la course.
 *
 * Le serveur répond 409 quand un autre a été plus rapide — premier arrivé, premier servi. Ce n'est
 * pas une panne, et l'écran doit le dire autrement : un prestataire qui lit « une erreur est
 * survenue » croit à un bug de l'application, pas à une course déjà partie.
 */
export function useAcceptAsapOffer() {
  const qc = useQueryClient();

  return useMutation<{ booking_id: number | null }, ApiError, number>({
    mutationFn: async (requestId) => {
      const res = await apiClient.post(`/provider/asap-offers/${requestId}/accept`);

      return { booking_id: res.data?.data?.booking_id ?? null };
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: QUERY_KEY });
      // La course acceptée devient une mission : la liste des missions doit la voir arriver.
      qc.invalidateQueries({ queryKey: ['provider', 'missions'] });
    },
  });
}

/**
 * Passer son tour.
 *
 * Le refus part au SERVEUR et n'est pas seulement retiré de l'écran : sans trace, la course serait
 * reproposée au prochain élargissement de rayon, et refusée à nouveau.
 */
export function useDeclineAsapOffer() {
  const qc = useQueryClient();

  return useMutation<void, ApiError, { requestId: number; reason?: string }>({
    mutationFn: async ({ requestId, reason }) => {
      await apiClient.post(`/provider/asap-offers/${requestId}/decline`, reason ? { reason } : {});
    },
    onSettled: () => qc.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}
