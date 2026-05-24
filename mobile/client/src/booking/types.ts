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
  created_at: string;
}

export interface BookingState {
  serviceId: number | null;
  serviceName: string;
  categorySlug: string;
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
  | { type: 'RESET' };
