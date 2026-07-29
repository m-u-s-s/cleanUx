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
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] }),
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
