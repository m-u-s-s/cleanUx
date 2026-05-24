export interface MissionAssignment {
  id: number;
  booking_id: number;
  service_name: string;
  client_name: string;
  address: string;
  city: string;
  scheduled_date: string;
  scheduled_time: string;
  estimated_duration_minutes?: number;
  distance_km?: number;
  created_at: string;
}

export interface Mission {
  id: number;
  status:
    | 'pending'
    | 'assigned'
    | 'en_route'
    | 'arrived'
    | 'in_progress'
    | 'completed'
    | 'cancelled';
  service_name: string;
  client_name: string;
  client_phone?: string;
  address: string;
  city: string;
  postal_code: string;
  latitude?: number;
  longitude?: number;
  scheduled_date: string;
  scheduled_time: string;
  total_price?: number;
  notes?: string;
}

export type MissionLifecycleAction = 'start' | 'arrive' | 'complete';
