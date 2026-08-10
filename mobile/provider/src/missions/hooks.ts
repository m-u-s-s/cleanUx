import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useChannel } from '@/realtime';
import type { MissionAssignment, Mission, MissionLifecycleAction } from './types';

// `enabled` par défaut à `true` : aucun appelant existant (DashboardScreen, MissionInboxScreen,
// ProviderMap, HomeScreen) n'a besoin de changer. DashboardActionsSheet le passe à `false` tant
// que le sheet est fermé — sinon le polling 15s tournerait pour un contenu hors écran.
export function useMissionInbox(enabled: boolean = true) {
  return useQuery<MissionAssignment[]>({
    queryKey: ['provider', 'assignments', 'inbox'],
    queryFn: async () => {
      const res = await apiClient.get('/provider/assignments/inbox');
      return res.data.data ?? res.data;
    },
    refetchInterval: 15000,
    enabled,
  });
}

export function useAcceptMission() {
  const qc = useQueryClient();
  return useMutation<void, ApiError, number>({
    mutationFn: async (assignmentId) => {
      await apiClient.post(`/provider/assignments/${assignmentId}/accept`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['provider', 'assignments'] }),
  });
}

export function useDeclineMission() {
  const qc = useQueryClient();
  return useMutation<void, ApiError, number>({
    mutationFn: async (assignmentId) => {
      await apiClient.post(`/provider/assignments/${assignmentId}/decline`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['provider', 'assignments'] }),
  });
}

export function useMissionDetail(missionId: number | null) {
  return useQuery<Mission>({
    queryKey: ['provider', 'mission', missionId],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}`);
      return res.data.data ?? res.data;
    },
    enabled: missionId !== null,
  });
}

/**
 * Accepts either a bare action ('start' | 'arrive' | 'complete') for backward compatibility,
 * or an object carrying the end-of-mission verification code, e.g.
 * `{ action: 'complete', code: '482951' }`. The code is sent as `end_code` so the server can
 * validate it (E2 — previously the code was collected in the UI but never transmitted).
 */
export type MissionLifecyclePayload =
  | MissionLifecycleAction
  | { action: MissionLifecycleAction; code?: string };

/**
 * RENVOYER AU CLIENT LE CODE QU'IL N'A PAS REÇU.
 *
 * Un SMS se perd : réseau du client, numéro mal saisi, message noyé, plafond d'envoi atteint. Sans
 * ce geste, l'intervention s'arrêtait là — le prestataire devant la porte, le client sans ses six
 * chiffres, et pour seul recours l'annulation de la mission.
 *
 * Le serveur invalide le code précédent et impose une attente entre deux renvois : le plafond du
 * module SMS est de cinq messages par heure et par numéro, et trois pressions distraites
 * suffiraient à l'épuiser — après quoi le client ne recevrait plus rien du tout.
 */
export function useResendMissionCode(missionId: number) {
  return useMutation<{ sent_to?: string }, ApiError, 'start' | 'end'>({
    mutationFn: async (type) =>
      (await apiClient.post(`/provider/missions/${missionId}/codes/resend`, { type })).data,
  });
}

export function useMissionLifecycle(missionId: number) {
  const qc = useQueryClient();
  return useMutation<void, ApiError, MissionLifecyclePayload>({
    mutationFn: async (payload) => {
      const action = typeof payload === 'string' ? payload : payload.action;
      const code = typeof payload === 'string' ? undefined : payload.code;
      // Chaque action porte son propre nom de code : le serveur valide `start_code` au
      // démarrage et `end_code` à la clôture, tous deux communiqués au client par SMS.
      const body = code
        ? action === 'complete'
          ? { end_code: code }
          : action === 'begin'
            ? { start_code: code }
            : undefined
        : undefined;
      await apiClient.post(`/provider/missions/${missionId}/${action}`, body);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });

      /*
       * LA LISTE DES MISSIONS ACTIVES AUSSI, et c'est elle qui déclenche le suivi en direct.
       *
       * `TripTrackingHost` s'appuie dessus pour savoir qu'une mission est passée `en_route` et
       * ouvrir la session GPS. Sans cette invalidation, il l'apprendrait au prochain sondage —
       * jusqu'à trente secondes pendant lesquelles le client ne voit rien bouger, précisément au
       * moment où il vient de recevoir « votre prestataire est en route ».
       */
      qc.invalidateQueries({ queryKey: ['provider', 'missions', 'active'] });
    },
  });
}

export function useLiveMissionUpdates(
  missionId: number | null,
  onUpdate: () => void,
) {
  useChannel(missionId ? `private-mission.${missionId}` : null, {
    MissionStatusUpdated: onUpdate,
  });
}
