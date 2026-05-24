import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

interface LoginInput { email: string; password: string; deviceName?: string; }
interface LoginResult { token: string; user: User; }

export function useLogin() {
  return useMutation<LoginResult, ApiError, LoginInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/login', {
        email: input.email, password: input.password,
        device_name: input.deviceName ?? 'cleanux-mobile',
      });
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
