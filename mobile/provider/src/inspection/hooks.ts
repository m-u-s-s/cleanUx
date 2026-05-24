import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

export interface ChecklistItem { id: number; label: string; type: string; completed: boolean; value?: any; }
export interface Inspection { id: number; mission_id: number; status: string; items: ChecklistItem[]; }

export function useInspection(missionId: number | null) {
  return useQuery<Inspection>({
    queryKey: ['inspection', missionId],
    queryFn: async () => (await apiClient.get(`/provider/missions/${missionId}/inspections`)).data.data?.[0] ?? (await apiClient.get(`/provider/missions/${missionId}/inspections`)).data,
    enabled: missionId !== null,
  });
}

export function useToggleChecklistItem() {
  const qc = useQueryClient();
  return useMutation<void, ApiError, { inspectionId: number; itemId: number; value: any }>({
    mutationFn: async ({ inspectionId, itemId, value }) => {
      await apiClient.put(`/inspections/${inspectionId}/items/${itemId}`, { value });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['inspection'] }),
  });
}

export function useSubmitInspection(inspectionId: number) {
  return useMutation<void, ApiError>({
    mutationFn: async () => { await apiClient.post(`/inspections/${inspectionId}/submit`); },
  });
}
