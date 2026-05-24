import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

interface RegisterInput {
  name: string; email: string; password: string; passwordConfirmation: string;
  phone?: string; locale?: string; acceptTerms: boolean; deviceName?: string;
}
interface RegisterResult { token: string; user: User; }

export function useRegister() {
  return useMutation<RegisterResult, ApiError, RegisterInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/register', {
        name: input.name, email: input.email, password: input.password,
        password_confirmation: input.passwordConfirmation,
        phone: input.phone, locale: input.locale, accept_terms: input.acceptTerms,
        device_name: input.deviceName ?? 'cleanux-mobile',
      });
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
