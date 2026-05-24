export interface TrackingPoint {
  latitude: number;
  longitude: number;
  speed?: number;
  heading?: number;
  recorded_at: string;
}

export interface TrackingSession {
  id: number;
  booking_id: number;
  status: 'enroute' | 'arrived' | 'in_mission' | 'ended' | 'cancelled';
  eta_minutes?: number;
  distance_km?: number;
  points: TrackingPoint[];
}

export interface LivePosition {
  latitude: number;
  longitude: number;
  speed?: number;
  heading?: number;
}

export interface LiveEta {
  eta_minutes: number;
  distance_km: number;
}
