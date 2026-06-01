import { useQuery, useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type {
  Service,
  ServiceCategory,
  Provider,
  Booking,
  BookingState,
  BookingFavoriteSummary,
} from './types';

export function useServiceCatalog() {
  return useQuery<ServiceCategory[]>({
    queryKey: ['services', 'categories'],
    queryFn: async () => {
      const res = await apiClient.get('/search/services');
      return res.data.data ?? res.data;
    },
    staleTime: 10 * 60 * 1000,
  });
}

export function usePricingServices(categorySlug?: string) {
  return useQuery<Service[]>({
    queryKey: ['pricing', 'services', categorySlug],
    queryFn: async () => {
      const res = await apiClient.get('/v2/pricing/services', { params: { category: categorySlug } });
      return res.data.data ?? res.data;
    },
    enabled: !!categorySlug,
    staleTime: 10 * 60 * 1000,
  });
}

export function useBrowseProviders(filters: { trade?: string; postalCode?: string; minRating?: number; page?: number }) {
  return useQuery<{ data: Provider[]; meta?: unknown }>({
    queryKey: ['providers', 'browse', filters],
    queryFn: async () => {
      const res = await apiClient.get('/search/providers', { params: filters });
      return res.data;
    },
    enabled: !!filters.trade || !!filters.postalCode,
  });
}

export function useAddressAutocomplete(query: string) {
  return useQuery<Array<{ label: string; place_id: string; lat?: number; lng?: number }>>({
    queryKey: ['geo', 'autocomplete', query],
    queryFn: async () => {
      const res = await apiClient.get('/v2/geo/autocomplete', { params: { q: query } });
      return res.data.data ?? res.data;
    },
    enabled: query.length >= 3,
    staleTime: 5 * 60 * 1000,
  });
}

export function usePostalAutocomplete(query: string) {
  return useQuery<Array<{ postal_code: string; city: string }>>({
    queryKey: ['search', 'postal', query],
    queryFn: async () => {
      const res = await apiClient.get('/search/postal-autocomplete', { params: { q: query } });
      return res.data.data ?? res.data;
    },
    enabled: query.length >= 2,
  });
}

/**
 * SP2 — list the client's favourite providers for 1-click re-booking.
 * Backend: GET /client/favorites (BookingFavoriteController@index).
 */
export function useBookingFavorites() {
  return useQuery<BookingFavoriteSummary[]>({
    queryKey: ['client', 'favorites'],
    queryFn: async () => {
      const res = await apiClient.get('/client/favorites');
      return res.data.data ?? res.data;
    },
    staleTime: 5 * 60 * 1000,
  });
}

type CreateBookingInput = Omit<
  BookingState,
  'serviceName' | 'categorySlug' | 'providerTypePreference' | 'preferredProviderUserId'
> & {
  serviceId: number;
  // SP2 — optional so existing callers keep working; default to 'any'/null.
  providerTypePreference?: BookingState['providerTypePreference'];
  preferredProviderUserId?: BookingState['preferredProviderUserId'];
};

export function useCreateBooking() {
  return useMutation<Booking, ApiError, CreateBookingInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/client/bookings', {
        service_catalog_id: input.serviceId,
        address: input.coordinates.address,
        city: input.coordinates.city,
        postal_code: input.coordinates.postalCode,
        latitude: input.coordinates.latitude,
        longitude: input.coordinates.longitude,
        scheduled_date: input.scheduling.date,
        scheduled_time: input.scheduling.time,
        is_asap: input.scheduling.isAsap,
        recurrence: input.scheduling.recurrence,
        surface: input.details.surface,
        frequency: input.details.frequency,
        options: input.details.options,
        comment: input.details.comment,
        // SP2 — client provider selection. The backend reads these 2 keys.
        provider_type_preference: input.providerTypePreference ?? 'any',
        preferred_provider_user_id: input.preferredProviderUserId ?? null,
      });
      return res.data.data ?? res.data;
    },
  });
}

export function useBookings() {
  return useQuery<Booking[]>({
    queryKey: ['client', 'bookings'],
    queryFn: async () => {
      const res = await apiClient.get('/client/bookings');
      return res.data.data ?? res.data;
    },
  });
}

export function useBookingDetail(id: number | null) {
  return useQuery<Booking>({
    queryKey: ['client', 'bookings', id],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${id}`);
      return res.data.data ?? res.data;
    },
    enabled: id !== null,
  });
}
