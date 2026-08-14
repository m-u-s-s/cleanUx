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
  /**
   * Le serveur le renvoie depuis toujours, ce type l'ignorait. C'est pourtant la clé du suivi :
   * les sessions GPS partagées avec le client sont portées par la RÉSERVATION, pas par la
   * mission — sans lui, aucun écran ne pouvait ouvrir la bonne session.
   */
  booking_id?: number | null;
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
  /**
   * QUEL PARCOURS DÉROULER — tranché par le serveur, une fois.
   *
   * Sans ce drapeau, chaque écran devrait le deviner (coordonnées de dépose présentes ? nom du
   * métier ?) et chacun devinerait à sa façon. Le premier à se tromper afficherait un champ de
   * code à un conducteur au volant.
   */
  is_ride?: boolean;
  /** Le point de dépose, pour la carte et pour dire au conducteur où il va. */
  dropoff?: {
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    distance_m: number | null;
  } | null;
  /**
   * L'INSTANT à partir duquel l'absence du client peut être déclarée.
   *
   * Une date, pas une durée : un décompte envoyé en secondes se remettrait à zéro à chaque
   * rechargement de l'écran, et il suffirait d'actualiser pour déclarer un passager absent au bout
   * de trois secondes.
   */
  no_show_available_at?: string | null;
}

/**
 * `start` met EN ROUTE (setEnRoute côté serveur), il ne démarre pas la mission.
 * `begin` fait arrived → started, contre le code communiqué au client par SMS à l'arrivée.
 */
/**
 * `start` met EN ROUTE, `arrive` signale l'arrivée, `begin` démarre contre le code, `complete`
 * clôture contre le code.
 *
 * `ride/start` et `ride/complete` sont l'autre parcours : celui des courses, où le client monte
 * dans la voiture et où il n'y a AUCUN code. Les deux jeux sont mutuellement exclusifs, et le
 * serveur répond 409 à qui se trompe de porte — plutôt que d'échouer en silence sur un code
 * inexistant.
 */
export type MissionLifecycleAction =
  | 'start'
  | 'arrive'
  | 'begin'
  | 'complete'
  | 'ride/start'
  | 'ride/complete';
