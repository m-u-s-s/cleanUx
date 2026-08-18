import { useMutation, useQuery } from '@tanstack/react-query';
import { apiClient, ApiError } from '../api';

/**
 * ANNULER — le questionnaire, le devis, l'exécution. Partagé par les deux applications.
 *
 * ── POURQUOI PARTAGÉ ─────────────────────────────────────────────────────────────────────────
 *
 * Client et prestataire posent des questions différentes, mais empruntent le MÊME tuyau : les
 * routes ne diffèrent que par un segment de rôle. Deux copies auraient donné deux façons d'annuler
 * la même réservation, et l'une aurait fini par diverger — c'est exactement ce qui a été évité
 * côté web avec un composant unique.
 *
 * ── UNE OPTION NON SOUTENUE N'ARRIVE PAS JUSQU'ICI ───────────────────────────────────────────
 *
 * Le serveur ne sert que les réponses dont le fait est vérifié : « le prestataire est en retard »
 * n'apparaît que si l'heure prévue est réellement dépassée. L'application n'a donc rien à filtrer,
 * et surtout rien à deviner.
 */

export type AudienceAnnulation = 'client' | 'provider';

export interface OptionDAnnulation {
  code: string;
  label: string;
  /** `cancel` annule ; tout le reste RENVOIE ailleurs — et l'écran doit le dire avant d'agir. */
  outcome: string;
  requires_text: boolean;
  requires_proof: boolean;
  redirects: boolean;
}

export interface QuestionDAnnulation {
  code: string;
  label: string;
  help_text: string | null;
  options: OptionDAnnulation[];
}

export interface DevisDAnnulation {
  fee_amount_cents: number;
  refund_amount_cents: number;
  currency: string;
  exempt_applied: boolean;
  tier_label: string | null;
  warnings: string[];
}

const racine = (audience: AudienceAnnulation, bookingId: number) =>
  `/v2/${audience}/bookings/${bookingId}`;

export function useQuestionnaireDAnnulation(audience: AudienceAnnulation, bookingId: number | null) {
  return useQuery<QuestionDAnnulation[]>({
    queryKey: ['cancellation', audience, bookingId, 'questionnaire'],
    queryFn: async () =>
      (await apiClient.get(`${racine(audience, bookingId as number)}/cancellation-questionnaire`)).data
        .questions ?? [],
    enabled: bookingId !== null,
  });
}

/**
 * LE DEVIS D'ANNULATION — ce qu'on prélèvera, pas une estimation.
 *
 * Il dépend du MOTIF : un motif exempté met les frais à zéro, et son plafond par personne peut les
 * ramener au palier normal. Le demander sans motif afficherait un montant que la confirmation
 * démentirait.
 */
export function useDevisDAnnulation(
  audience: AudienceAnnulation,
  bookingId: number | null,
  reasonCode: string | null,
) {
  return useQuery<DevisDAnnulation | null>({
    queryKey: ['cancellation', audience, bookingId, 'quote', reasonCode],
    queryFn: async () => {
      const res = await apiClient.get(`${racine(audience, bookingId as number)}/cancellation-quote`, {
        params: { reason_code: reasonCode },
      });

      return res.data.quote ?? null;
    },
    enabled: bookingId !== null && reasonCode !== null,
  });
}

export function useAnnulerLaReservation(audience: AudienceAnnulation, bookingId: number | null) {
  return useMutation<unknown, ApiError, { reasonCode: string; reasonText?: string }>({
    mutationFn: async ({ reasonCode, reasonText }) =>
      (
        await apiClient.post(`${racine(audience, bookingId as number)}/cancel`, {
          reason_code: reasonCode,
          reason_text: reasonText ?? null,
        })
      ).data,
  });
}
