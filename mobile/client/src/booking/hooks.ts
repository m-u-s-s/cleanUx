import { useQuery, useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type {
  Service,
  ServiceCategory,
  Provider,
  Booking,
  BookingState,
  BookingFavoriteSummary,
  EligibleCompany,
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

/**
 * LA FORME QUE REND VRAIMENT `/search/providers`.
 *
 * Elle ne ressemble pas à `Provider`, et rien ne le signalait : le type déclarait `rating_avg`,
 * `review_count`, `avatar_url` et des métiers en chaînes, quand le serveur envoie `rating.avg`,
 * `rating.count`, `photo_url` et des métiers en objets. TypeScript validait l'écran contre un
 * contrat que personne ne tenait — la vérification s'arrête au `res.data` non typé.
 */
type ProviderDeLApi = {
  id: number;
  name: string;
  photo_url?: string | null;
  rating?: { avg: number | null; count: number } | null;
  trades?: Array<{ id: number; name: string; code: string }> | null;
  distance_km?: number | null;
  hourly_rate?: number | null;
};

function versProvider(brut: ProviderDeLApi): Provider {
  return {
    id: brut.id,
    name: brut.name,
    avatar_url: brut.photo_url ?? undefined,
    rating_avg: brut.rating?.avg ?? null,
    review_count: brut.rating?.count ?? 0,
    // Les métiers arrivent en objets ; la carte n'affiche que leur nom.
    trades: (brut.trades ?? []).map(t => t.name).filter(Boolean),
    distance_km: brut.distance_km ?? undefined,
    hourly_rate: brut.hourly_rate ?? undefined,
  };
}

export function useBrowseProviders(filters: { trade?: string; postalCode?: string; minRating?: number; page?: number }) {
  return useQuery<{ data: Provider[]; meta?: unknown }>({
    queryKey: ['providers', 'browse', filters],
    queryFn: async () => {
      /*
       * LES NOMS DE PARAMÈTRES SONT CEUX DU SERVEUR, pas ceux de l'écran.
       *
       * `postalCode` partait tel quel ; la validation du contrôleur, qui attend `postal_code`,
       * l'écartait sans rien dire. Le filtre était donc inerte : on saisissait un code postal et
       * la recherche ramenait l'annuaire entier, page par page.
       */
      const res = await apiClient.get('/search/providers', {
        params: {
          trade: filters.trade,
          postal_code: filters.postalCode,
          min_rating: filters.minRating,
          page: filters.page,
        },
      });

      return { data: (res.data?.data ?? []).map(versProvider), meta: res.data?.meta };
    },
    enabled: !!filters.trade || !!filters.postalCode,
  });
}

/**
 * SP3 Task 9 — list provider COMPANIES eligible for a (zone + trade) context.
 * Backend: GET /client/companies (CompanyDirectoryController). The zone can be
 * supplied either as a technical `service_zone_id` OR as a `postal_code` string
 * (resolved server-side, 422 if not covered); `service_catalog_id` optional.
 * Returns { companies, loading, error }.
 *
 * Mirrors useBrowseProviders (SP2): the request only fires when a zone is known
 * (id or postal), and the backend stays the authoritative premium + eligibility
 * gate. When both a zone id and a postal are present we prefer the zone id.
 */
export function useEligibleCompanies(args: {
  serviceZoneId?: number | null;
  postalCode?: string | null;
  serviceCatalogId?: number | null;
}) {
  const { serviceZoneId = null, postalCode = null, serviceCatalogId = null } = args;
  const hasZone = serviceZoneId != null || !!postalCode;

  const query = useQuery<EligibleCompany[]>({
    queryKey: ['client', 'companies', serviceZoneId, postalCode ?? null, serviceCatalogId ?? null],
    queryFn: async () => {
      const params: Record<string, string | number> = {};
      if (serviceZoneId != null) {
        params['service_zone_id'] = serviceZoneId;
      } else if (postalCode) {
        params['postal_code'] = postalCode;
      }
      if (serviceCatalogId != null) params['service_catalog_id'] = serviceCatalogId;
      const res = await apiClient.get('/client/companies', { params });
      return res.data.data ?? res.data;
    },
    enabled: hasZone,
  });

  return {
    companies: query.data ?? [],
    loading: query.isLoading,
    error: query.error ?? null,
  };
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

/*
 * `useCreateBooking` A ETE SUPPRIME, ET C'EST UNE INFORMATION.
 *
 * Il postait sur `/client/bookings` et n'avait AUCUN APPELANT : tous les points d'entree de
 * reservation ouvrent la WebView `/commander` (`EmbeddedModule`), conformement a la strategie
 * hybride. Le hook, son type d'entree et ses trois tests donnaient donc l'illusion d'un parcours
 * de reservation natif qui n'existe nulle part -- exactement le genre de code mort qui trompe le
 * prochain lecteur, et que `tsc` comme jest declaraient sains.
 *
 * Le PAIEMENT, lui, est bien natif et joignable : `BookingDetailScreen` mene a `PaymentCheckout`,
 * qui passe par `BookingPaymentController` et le meme service que le web. Ne pas confondre les
 * deux -- l'un etait mort, l'autre porte de l'argent reel.
 *
 * Le jour ou la reservation native sera decidee, elle se reecrira contre l'API d'alors. Garder une
 * version jamais appelee ne fait pas gagner ce travail : elle vieillit sans que rien ne le dise.
 */

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
