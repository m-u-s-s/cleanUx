import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

interface RatingInput {
  bookingId: number;
  overall: number;
  punctuality?: number;
  quality?: number;
  communication?: number;
  value?: number;
  comment?: string;
}

export function useSubmitRating() {
  return useMutation<void, ApiError, RatingInput>({
    mutationFn: async (input) => {
      await apiClient.post(`/client/bookings/${input.bookingId}/rating`, {
        overall: input.overall,
        punctuality: input.punctuality,
        quality: input.quality,
        communication: input.communication,
        value: input.value,
        comment: input.comment,
      });
    },
  });
}
