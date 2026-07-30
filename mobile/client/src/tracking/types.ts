/**
 * Formes du suivi de mission.
 *
 * Deux vocabulaires se rencontrent ici. Le serveur parle `lat`/`lng` et enveloppe ses réponses
 * dans `data` ; les écrans parlent celui de react-native-maps — `latitude`/`longitude`. Les types
 * décrivaient jusqu'ici une troisième forme qui n'existait nulle part : ni le serveur ni les
 * écrans ne l'employaient, si bien que TypeScript validait une traduction que personne ne faisait.
 * Chaque champ lu par la carte valait `undefined`, et la carte ne pouvait donc jamais s'afficher.
 *
 * La traduction se fait désormais une seule fois, dans les crochets. Ces types décrivent les deux
 * bouts pour que l'écart ne puisse plus repasser inaperçu.
 */

export type TrackingStatus = 'enroute' | 'arrived' | 'in_mission' | 'ended' | 'cancelled';

/** Ce que renvoie `/client/bookings/{id}/tracking/trail`, littéralement. */
export interface ApiTrackingPoint {
  lat: number;
  lng: number;
  eta_seconds: number | null;
  distance_to_dest_m: number | null;
  at: string;
}

/** Ce que renvoie `/client/bookings/{id}/tracking`, littéralement. */
export interface ApiTrackingSession {
  code: string;
  status: TrackingStatus;
  destination: { lat: number | null; lng: number | null };
  provider: { lat: number | null; lng: number | null; speed_mps: number | null };
  eta_seconds: number | null;
  eta_minutes: number | null;
  arrived_at: string | null;
  in_mission_at: string | null;
  last_ping_at: string | null;
  presence_confirmed_at: string | null;
}

/**
 * Code de présence affiché par le client et scanné par le prestataire.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit. Scanner exige les deux appareils au même endroit.
 */
export interface PresenceCode {
  session_id: number;
  session_code: string;
  code: string;
  expires_at: string;
}

/** Code de fin de prestation : le client atteste que le travail est fait. */
export interface CompletionCode {
  mission_id: number;
  code: string;
  expires_at: string;
}

export interface LivePosition {
  latitude: number;
  longitude: number;
  speed?: number;
  heading?: number;
}

export interface TrackingPoint extends LivePosition {
  eta_seconds: number | null;
  distance_to_dest_m: number | null;
  recorded_at: string;
}

/** La session telle que la consomment les écrans. */
export interface TrackingSession {
  code: string;
  status: TrackingStatus;
  /** Là où se rend le prestataire — absent tant que la réservation n'est pas géocodée. */
  destination: LivePosition | null;
  /** Sa dernière position connue. Fait foi quand la trace est vide. */
  provider: LivePosition | null;
  eta_seconds: number | null;
  eta_minutes: number | null;
  arrived_at: string | null;
  in_mission_at: string | null;
  last_ping_at: string | null;
  /** Renseigné dès que le prestataire a scanné le code affiché par le client. */
  presence_confirmed_at: string | null;
}

export interface LiveEta {
  eta_minutes: number;
  distance_km: number;
}
