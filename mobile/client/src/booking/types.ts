export interface ServiceCategory {
  id: number;
  name: string;
  slug: string;
  icon?: string;
}

export interface Service {
  id: number;
  name: string;
  slug: string;
  category_id: number;
  base_price?: number;
  unit?: string;
  description?: string;
}

export interface Provider {
  id: number;
  name: string;
  avatar_url?: string;
  rating_avg: number;
  review_count: number;
  trades: string[];
  distance_km?: number;
  hourly_rate?: number;
}

export interface Booking {
  id: number;
  status: string;
  service_name: string;
  scheduled_date: string;
  scheduled_time: string;
  address: string;
  city: string;
  postal_code: string;
  total_price?: number;
  provider_name?: string;
  contract_covered?: boolean;
  contract_label?: string | null;
  created_at: string;
}

export type ProviderTypePreference = 'any' | 'independent' | 'company';

// SP3 Task 9 — provider COMPANY eligible for a (zone + trade) context.
// Shape mirrors GET /client/companies (CompanyDirectoryController).
export interface EligibleCompany {
  id: number;
  name: string;
  rating_avg: number | null;
  rating_count: number;
  providers_count: number;
}

export interface BookingFavoriteSummary {
  id: number;
  label: string | null;
  preferred_provider: { id: number; name: string } | null;
}

export interface BookingState {
  serviceId: number | null;
  serviceName: string;
  categorySlug: string;
  // SP2 — client provider selection
  providerTypePreference: ProviderTypePreference;
  preferredProviderUserId: number | null;
  // SP3 Task 9 — premium pick of a provider COMPANY. Mutually exclusive with
  // preferredProviderUserId: setting one clears the other.
  assignedProviderOrganizationId: number | null;
  details: {
    surface?: number;
    frequency?: string;
    options: string[];
    comment: string;
  };
  coordinates: {
    address: string;
    city: string;
    postalCode: string;
    latitude?: number;
    longitude?: number;
  };
  scheduling: {
    date: string;
    time: string;
    isAsap: boolean;
    recurrence?: string;
  };
}

export type BookingAction =
  | { type: 'SET_SERVICE'; serviceId: number; serviceName: string; categorySlug: string }
  | { type: 'SET_DETAILS'; details: BookingState['details'] }
  | { type: 'SET_COORDINATES'; coordinates: BookingState['coordinates'] }
  | { type: 'SET_SCHEDULING'; scheduling: BookingState['scheduling'] }
  | { type: 'SET_PROVIDER_TYPE'; providerTypePreference: ProviderTypePreference }
  | { type: 'SET_PREFERRED_PROVIDER'; preferredProviderUserId: number | null }
  | { type: 'SET_PREFERRED_COMPANY'; assignedProviderOrganizationId: number | null }
  | { type: 'RESET' };
