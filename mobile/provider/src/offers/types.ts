/**
 * L'offre telle que le serveur la décrit — une seule forme pour les trois canaux.
 *
 * Le temps réel, le push et l'appel de repli livrent EXACTEMENT cette charge utile
 * (`App\Services\Dispatch\OfferPayloadBuilder`). Trois formes différentes feraient afficher trois
 * écrans différents selon le canal par lequel l'offre est arrivée.
 */
export interface MissionOffer {
  assignment_id: number;
  mission_id: number;
  booking_id: number | null;
  booking_mode: string;
  trade_name: string | null;
  service_name: string | null;
  client_name: string | null;
  /** Ville et code postal seulement : l'adresse exacte est le prix de l'acceptation. */
  approximate_address: string | null;
  city: string | null;
  postal_code: string | null;
  scheduled_at: string | null;
  estimated_duration_minutes: number | null;
  payout_cents: number | null;
  /** Ce qu'il reste à rouler pour ALLER CHERCHER le client. */
  distance_m: number | null;
  distance_km: number | null;
  /**
   * SUR UNE COURSE, DEUX DISTANCES DÉCIDENT — et une seule voyageait.
   *
   * Un chauffeur voyait la rémunération sans savoir s'il s'engageait pour deux kilomètres ou pour
   * quarante. C'est pourtant la question qui décide d'accepter.
   */
  is_ride?: boolean;
  ride_distance_m?: number | null;
  ride_distance_km?: number | null;
  ride_duration_minutes?: number | null;
  latitude: number | null;
  longitude: number | null;
  /**
   * L'HORLOGE DU SERVEUR FAIT AUTORITÉ. Le compte à rebours se calcule sur cette date, jamais sur
   * un nombre de secondes figé à l'émission : le réseau met ce qu'il met, et un téléphone dont
   * l'heure dérive afficherait une offre éternelle ou déjà morte.
   */
  expires_at: string | null;
  ttl_seconds: number | null;
  sent_at: string | null;
}
