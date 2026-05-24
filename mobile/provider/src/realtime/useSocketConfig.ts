import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface SocketConfig {
  driver: string; key: string; host: string; port: number; scheme: string; auth_endpoint: string;
}

export function useSocketConfig(enabled = true) {
  return useQuery<SocketConfig>({
    queryKey: ['realtime', 'socket-config'],
    queryFn: async () => (await apiClient.get('/realtime/socket-config')).data,
    enabled,
    staleTime: 30 * 60 * 1000,
  });
}
