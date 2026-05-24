import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

export function useRequestOtp() {
  return useMutation<void, ApiError, { phone: string }>({
    mutationFn: async ({ phone }) => { await apiClient.post('/phone/verify-request', { phone }); },
  });
}

export function useConfirmOtp() {
  return useMutation<void, ApiError, { phone: string; code: string }>({
    mutationFn: async ({ phone, code }) => { await apiClient.post('/phone/verify-confirm', { phone, code }); },
  });
}
