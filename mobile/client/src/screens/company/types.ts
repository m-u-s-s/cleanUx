/**
 * Les formes servies par `/api/client/company/*`.
 *
 * Rassemblées ici parce que deux écrans lisent la même réservation — l'accueil et la liste — et
 * qu'une forme recopiée dans chacun dérive au premier champ ajouté côté serveur.
 */

export interface ReservationSociete {
  id: number;
  reference: string | null;
  status: string;
  site: string | null;
  provider: string | null;
  scheduled_at: string | null;
  /**
   * `bookings` ne porte pas de `total_amount` — le nom que j'avais d'abord écrit côté serveur.
   * La colonne documentée et castée par le modèle est `estimated_price` ; `final_price` existe en
   * base sans être déclarée sur le modèle, donc l'API ne la sert pas.
   */
  estimated_price: number | null;
}

export interface LocalSociete {
  id: number;
  name: string;
  address: string | null;
  city: string | null;
  postal_code: string | null;
  status: string;
  surface_m2: number | null;
  floor_count: number | null;
  contact_name: string | null;
  contact_phone: string | null;
  service_frequency: string | null;
  active_bookings_count: number;
}

export interface MembreSociete {
  id: number;
  user_id: number;
  name: string | null;
  email: string | null;
  role: string;
  status: string;
  joined_at: string | null;
}

export interface ContratSociete {
  id: number;
  reference: string | null;
  status: string;
  provider: string | null;
  billing_cycle: string | null;
  effective_from: string | null;
  effective_to: string | null;
  payment_terms_days: number | null;
}

export interface FactureSociete {
  id: number;
  invoice_number: string | null;
  status: string;
  currency: string | null;
  total_amount: number;
  balance_due: number;
  issued_at: string | null;
  due_at: string | null;
}

export interface ResumeFacturation {
  unpaid: number;
  total_month: number;
  count_total: number;
}
