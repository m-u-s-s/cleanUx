import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

/**
 * CE QUE L'API ENVOIE VRAIMENT — vérifié contre `Api\Provider\AvailabilityController`.
 *
 * L'écran précédent lisait `res.data.data`, une clé que cette réponse n'a jamais eue : il
 * retombait sur l'objet entier `{slots, exceptions}` et le passait à une `FlatList`, qui affichait
 * « Aucune disponibilité » même avec des créneaux en base. Il lisait aussi `item.day_of_week`
 * quand la colonne s'appelle `weekday`. Deux lectures d'un contrat jamais relu.
 */
export interface AvailabilitySlot {
  id: number;
  /** 0 = dimanche … 6 = samedi. C'est la convention de Carbon, pas celle d'un tableau lundi-first. */
  weekday: number;
  start_time: string;
  end_time: string;
  timezone: string | null;
  is_active: boolean;
  valid_from: string | null;
  valid_until: string | null;
}

export type ExceptionType = 'closed' | 'open_override' | 'partial';

export interface AvailabilityException {
  id: number;
  date: string;
  exception_type: ExceptionType;
  start_time: string | null;
  end_time: string | null;
  reason: string | null;
}

export interface AvailabilityPayload {
  slots: AvailabilitySlot[];
  exceptions: AvailabilityException[];
}

export const AVAILABILITY_KEY = ['provider', 'availability'] as const;

export function useAvailability() {
  return useQuery<AvailabilityPayload>({
    queryKey: AVAILABILITY_KEY,
    queryFn: async () => {
      const res = await apiClient.get('/provider/availability');

      // Ceinture et bretelles : une réponse tronquée rend deux tableaux vides plutôt qu'un écran
      // qui explose sur `.map` d'un `undefined`.
      return {
        slots: res.data?.slots ?? [],
        exceptions: res.data?.exceptions ?? [],
      };
    },
  });
}

export interface SlotInput {
  weekday: number;
  start_time: string;
  end_time: string;
}

export function useCreateSlot() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (input: SlotInput) => apiClient.post('/provider/availability/slots', input),
    onSuccess: () => qc.invalidateQueries({ queryKey: AVAILABILITY_KEY }),
  });
}

export function useUpdateSlot() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...input }: Partial<SlotInput> & { id: number; is_active?: boolean }) =>
      apiClient.put(`/provider/availability/slots/${id}`, input),
    onSuccess: () => qc.invalidateQueries({ queryKey: AVAILABILITY_KEY }),
  });
}

export function useDeleteSlot() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => apiClient.delete(`/provider/availability/slots/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: AVAILABILITY_KEY }),
  });
}

export function useCloseDay() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: ({ date, reason }: { date: string; reason?: string }) =>
      apiClient.post('/provider/availability/exceptions', {
        date,
        exception_type: 'closed',
        reason: reason ?? null,
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: AVAILABILITY_KEY }),
  });
}

export function useDeleteException() {
  const qc = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => apiClient.delete(`/provider/availability/exceptions/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: AVAILABILITY_KEY }),
  });
}
