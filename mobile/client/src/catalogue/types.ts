/** Le catalogue servi par `GET /api/client/catalogue` — ce que le moteur de commande accepte. */
export type ModeCatalogue = 'asap' | 'scheduled';

export interface MetierDuCatalogue {
  slug: string;
  name: string;
  icon: string | null;
  short_description: string | null;
  /** Hors taxe. `null` : aucun prix plancher, le prix sort des réponses au questionnaire. */
  floor_price_cents: number | null;
  /** Le plancher est le prix d'une heure : il se lit par heure. */
  hourly: boolean;
}

export interface SecteurDuCatalogue {
  slug: string;
  name: string;
  icon: string | null;
  trades: MetierDuCatalogue[];
}

export interface ReponseCatalogue {
  mode: ModeCatalogue;
  zone_known: boolean;
  currency: string;
  sectors: SecteurDuCatalogue[];
}
