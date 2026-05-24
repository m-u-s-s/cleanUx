import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type { PaymentMethod, PaymentIntentResult, SetupIntentResult } from './types';

export function usePaymentMethods() {
  return useQuery<PaymentMethod[]>({
    queryKey: ['payment', 'methods'],
    queryFn: async () => {
      const res = await apiClient.get('/client/payment-methods');
      return res.data.data ?? res.data;
    },
  });
}

export function useSetupIntent() {
  return useMutation<SetupIntentResult, ApiError>({
    mutationFn: async () => {
      const res = await apiClient.post('/client/payment-methods/setup-intent');
      return res.data;
    },
  });
}

export function usePaymentIntent(bookingId: number) {
  return useMutation<PaymentIntentResult, ApiError>({
    mutationFn: async () => {
      const res = await apiClient.post(`/client/bookings/${bookingId}/payment-intent`);
      return res.data;
    },
  });
}

export function useDeletePaymentMethod() {
  const queryClient = useQueryClient();
  return useMutation<void, ApiError, string>({
    mutationFn: async (methodId) => {
      await apiClient.delete(`/client/payment-methods/${methodId}`);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['payment', 'methods'] }),
  });
}
