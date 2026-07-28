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
  estimated_duration_minutes?: number | null;
  // Destination de la mission — l'adresse du client, résolue côté serveur depuis
  // missions.destination_lat/lng puis, à défaut, bookings.destination_lat/lng.
  // Nulles tant que le géocodage n'a pas abouti. Ce n'est PAS missions.start_lat,
  // qui porte la position GPS du prestataire aux transitions arrived/started.
  latitude?: number | null;
  longitude?: number | null;
  created_at: string;
}

export interface Mission {
  id: number;
  /**
   * Statuts réellement écrits par la table `missions` (App\Support\Domain\MissionStatus) :
   * 'planned' et 'paused' manquaient à cette union alors que le backend les renvoie.
   * 'started' y est ajouté pour la même raison — c'est la valeur que la base utilise pour une
   * mission en cours. ATTENTION : 'pending' et 'in_progress' ne sont écrits par AUCUN chemin
   * backend ; ils sont conservés le temps que les écrans qui les testent soient réalignés
   * (MissionDetailScreen gate ses boutons d'action sur 'in_progress', voir le rapport).
   */
  status:
    | 'pending'
    | 'planned'
    | 'assigned'
    | 'en_route'
    | 'arrived'
    | 'started'
    | 'paused'
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
