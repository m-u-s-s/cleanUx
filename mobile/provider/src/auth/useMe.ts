import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import type { User } from '@/api/types';

export function useMe(enabled = true) {
  return useQuery<User>({
    queryKey: ['auth', 'me'],
    queryFn: async () => (await apiClient.get('/auth/me')).data.user,
    enabled,
    staleTime: 5 * 60 * 1000,
  });
}
