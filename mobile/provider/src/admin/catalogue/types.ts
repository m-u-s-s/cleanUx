/** Un métier, vu depuis une zone. */
export interface ZoneTrade {
  id: number;
  name: string;
  slug: string;
  sector: string | null;
  sector_id: number | null;
  /** Ouvert DANS CETTE ZONE. Un métier peut être publié partout et fermé ici. */
  is_open: boolean;
  base_rate_cents: number;
  /** Le tarif vient-il de la zone, ou du métier faute de mieux ? */
  has_zone_price: boolean;
}

export interface ZoneCatalog {
  zone: { id: number; name: string; country_id: number };
  trades: ZoneTrade[];
}
