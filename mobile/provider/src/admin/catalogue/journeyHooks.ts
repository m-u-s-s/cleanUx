import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface JourneyOption {
  id: number;
  label: string;
  value: string;
  price_modifier_cents: number;
  price_multiplier: number | null;
  duration_modifier_min: number;
  is_default: boolean;
}

export interface JourneyQuestion {
  id: number;
  code: string;
  label: string;
  help_text: string | null;
  type: string;
  is_required: boolean;
  sort_order: number;
  pricing: Record<string, unknown> | null;
  options: JourneyOption[];
}

export interface Journey {
  trade: { id: number; name: string; slug: string };
  data: JourneyQuestion[];
  publication: { can_publish: boolean; issues: unknown };
}

/**
 * Le parcours d'un métier : ses questions, leurs réponses, et le verdict de publication.
 *
 * LE VERDICT ARRIVE AVEC LE PARCOURS, pas à part. Sans lui, on règle des questions sans savoir si
 * l'ensemble partira — et le bouton « Publier » ne saurait pas s'il doit être offert.
 */
export function useJourney(tradeId: number) {
  return useQuery<Journey>({
    queryKey: ['admin', 'journey', tradeId],
    queryFn: async () => (await apiClient.get(`/admin/catalogue/trades/${tradeId}/journey`)).data,
  });
}

/**
 * Toutes les écritures du parcours, sous une seule fabrique.
 *
 * POURQUOI UNE SEULE. Sept opérations — ajouter une question, la modifier, la déplacer, la retirer,
 * ajouter une réponse, la régler, la retirer — partagent exactement la même conséquence : le
 * parcours a changé, il faut le relire. Sept mutations séparées auraient produit sept fois la même
 * invalidation, et l'oubli d'une seule aurait laissé un écran qui ment.
 */
export function useJourneyMutation(tradeId: number) {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: async (op: JourneyOperation) => {
      switch (op.type) {
        case 'question.create':
          return (await apiClient.post(`/admin/catalogue/trades/${tradeId}/questions`, op.values)).data;
        case 'question.update':
          return (await apiClient.patch(`/admin/catalogue/questions/${op.id}`, op.values)).data;
        case 'question.move':
          return (await apiClient.post(`/admin/catalogue/questions/${op.id}/move`, { direction: op.direction })).data;
        case 'question.remove':
          return (await apiClient.delete(`/admin/catalogue/questions/${op.id}`)).data;
        case 'option.create':
          return (await apiClient.post(`/admin/catalogue/questions/${op.id}/options`, op.values)).data;
        case 'option.update':
          return (await apiClient.patch(`/admin/catalogue/options/${op.id}`, op.values)).data;
        case 'option.remove':
          return (await apiClient.delete(`/admin/catalogue/options/${op.id}`)).data;
        case 'publish':
          return (await apiClient.post(`/admin/catalogue/trades/${tradeId}/publish`)).data;
      }
    },

    onSettled: () => qc.invalidateQueries({ queryKey: ['admin', 'journey', tradeId] }),
  });
}

export type JourneyOperation =
  | { type: 'question.create'; values: Record<string, unknown> }
  | { type: 'question.update'; id: number; values: Record<string, unknown> }
  | { type: 'question.move'; id: number; direction: -1 | 1 }
  | { type: 'question.remove'; id: number }
  | { type: 'option.create'; id: number; values: Record<string, unknown> }
  | { type: 'option.update'; id: number; values: Record<string, unknown> }
  | { type: 'option.remove'; id: number }
  | { type: 'publish' };
