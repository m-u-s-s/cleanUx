import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface AppNotification {
  id: string;
  type: string;
  title: string;
  body: string;
  read_at: string | null;
  created_at: string;
  data?: Record<string, unknown>;
}

export function useNotifications() {
  return useQuery<AppNotification[]>({
    queryKey: ['notifications'],
    queryFn: async () => {
      const res = await apiClient.get('/notifications');
      return res.data.data ?? res.data;
    },
    refetchInterval: 30000,
  });
}

export function useMarkAllRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => apiClient.post('/notifications/read-all'),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });
}
