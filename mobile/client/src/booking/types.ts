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
  /*
   * NULL VEUT DIRE « PAS ENCORE NOTÉ », et ce n'est pas zéro.
   *
   * Le type promettait un nombre. L'API, elle, rend `rating.avg` à null tant que personne n'a
   * noté : `rating_avg.toFixed(1)` levait alors « Cannot read property 'toFixed' of undefined »,
   * et l'écran « Explorer » tombait sur son écran d'erreur dès la première recherche aboutie.
   * Le remplacer par 0 aurait été pire qu'un plantage : afficher « ⭐ 0.0 » sur un prestataire
   * neuf, c'est le donner pour mauvais.
   */
  rating_avg: number | null;
  review_count: number;
  trades: string[];
  distance_km?: number;
  hourly_rate?: number;
}

export interface Booking {
  id: number;
  /** Valeur BRUTE du domaine : vocabulaire français (en_attente, confirme, en_route…). */
  status: string;
  /**
   * État normalisé par le serveur : pending | confirmed | in_progress | completed | cancelled |
   * unknown. C'est LUI qu'il faut filtrer — le statut brut mélange français et anglais, et
   * deviner ses valeurs faisait qu'une réservation `en_route` n'était jamais reconnue comme en
   * cours, si bien que la carte de suivi ne s'affichait jamais.
   */
  state?: string;
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

// `BookingAction` décrivait les actions du reducer de l'assistant en cinq étapes : il part avec
// lui, la commande se composant désormais côté serveur dans `order_drafts`.
//
// `BookingState` RESTE : il ne servait pas qu'au reducer, il type aussi la charge envoyée par
// `useCreateBooking`. Le retirer obligerait à redécrire cette forme ailleurs, à l'identique.
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
