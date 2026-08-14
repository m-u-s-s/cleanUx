import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { ZoneCatalog, ZoneTrade } from './types';

/**
 * Les métiers d'une zone, avec leur état d'ouverture.
 *
 * ELLE REND TOUS LES MÉTIERS, y compris ceux qui ne sont pas ouverts. L'absence de réglage est un
 * ÉTAT et non un trou : une liste qui ne montrerait que les métiers déjà réglés tairait exactement
 * ceux qu'on vient ouvrir.
 */
export function useZoneTrades(zoneId: number) {
  return useQuery<ZoneCatalog>({
    queryKey: ['admin', 'catalogue', 'zone', zoneId],
    queryFn: async () => {
      const { data } = await apiClient.get(`/admin/catalogue/zones/${zoneId}/trades`);

      return { zone: data.zone, trades: data.data ?? [] };
    },
  });
}

/**
 * Ouvre ou ferme un métier dans une zone.
 *
 * MISE À JOUR OPTIMISTE, puis vérité du serveur. Sur un téléphone en déplacement, attendre
 * l'aller-retour avant de bouger l'interrupteur donne l'impression que le geste n'a pas pris, et
 * l'administrateur appuie deux fois — ce qui rouvre ce qu'il venait de fermer.
 *
 * Le refus (un compte en lecture seule) fait revenir l'état d'avant : le serveur reste l'autorité,
 * l'affichage n'anticipe que ce qu'il peut annuler.
 */
export function useToggleTradeInZone(zoneId: number) {
  const qc = useQueryClient();
  const cle = ['admin', 'catalogue', 'zone', zoneId];

  return useMutation({
    mutationFn: async (tradeId: number) => {
      const { data } = await apiClient.post(`/admin/catalogue/zones/${zoneId}/trades/${tradeId}/toggle`);

      return data.data as { id: number; is_open: boolean; base_rate_cents: number };
    },

    onMutate: async (tradeId: number) => {
      await qc.cancelQueries({ queryKey: cle });
      const avant = qc.getQueryData<ZoneCatalog>(cle);

      qc.setQueryData<ZoneCatalog>(cle, (courant) =>
        courant
          ? {
              ...courant,
              trades: courant.trades.map((metier: ZoneTrade) =>
                metier.id === tradeId ? { ...metier, is_open: !metier.is_open } : metier,
              ),
            }
          : courant,
      );

      return { avant };
    },

    onError: (_erreur, _tradeId, contexte) => {
      if (contexte?.avant) {
        qc.setQueryData(cle, contexte.avant);
      }
    },

    onSettled: () => qc.invalidateQueries({ queryKey: cle }),
  });
}

/** Les cinq réglages du prix au kilomètre, tels qu'on les enregistre ENSEMBLE. */
export interface DistancePricing {
  distance_pricing_enabled: boolean;
  pickup_fee_cents: number;
  price_per_km_cents: number | null;
  price_per_minute_cents: number | null;
  included_km: number;
}

/**
 * Règle le prix au kilomètre d'un métier dans une zone.
 *
 * UN SEUL APPEL POUR LES CINQ VALEURS, et pas cinq bascules : prise en charge, prix au kilomètre et
 * kilomètres inclus n'ont de sens qu'ensemble. Les envoyer séparément laisserait, entre deux
 * requêtes, une grille qui facture des kilomètres sans prise en charge — un état qu'aucun exploitant
 * n'a décidé, sur des commandes en cours.
 *
 * PAS DE MISE À JOUR OPTIMISTE ici, contrairement à l'interrupteur d'ouverture : celui-ci bascule un
 * booléen qu'on sait annuler, celle-là engage un montant. Afficher un tarif avant que le serveur
 * l'ait accepté ferait croire à une grille qui n'existe pas.
 */
export function useUpdateDistancePricing(zoneId: number) {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: async ({ tradeId, valeurs }: { tradeId: number; valeurs: DistancePricing }) => {
      const { data } = await apiClient.post(
        `/admin/catalogue/zones/${zoneId}/trades/${tradeId}/distance-pricing`,
        valeurs,
      );

      return data.data as DistancePricing & { id: number };
    },

    onSettled: () => qc.invalidateQueries({ queryKey: ['admin', 'catalogue', 'zone', zoneId] }),
  });
}
