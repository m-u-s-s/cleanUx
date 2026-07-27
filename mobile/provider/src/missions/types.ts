export interface MissionAssignment {
  id: number;
  mission_id: number;
  assignment_status: string;
  assigned_at?: string | null;
  expires_at?: string | null;
  remaining_seconds?: number | null;
  booking_id: number;
  service_name: string | null;
  client_name: string | null;
  address: string | null;
  city: string | null;
  postal_code?: string | null;
  scheduled_date: string | null;
  scheduled_time: string | null;
  // Coordonnées de la mission (missions.start_lat/start_lng). Nulles si non géocodée.
  latitude?: number | null;
  longitude?: number | null;
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
