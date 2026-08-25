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
  allows_asap?: boolean;
  asap_enabled?: boolean;
  /**
   * Ce métier emmène-t-il d'un point à un autre ?
   *
   * Dérivé du parcours côté serveur (deux questions de localisation), jamais d'un drapeau posé à
   * côté. C'est lui qui décide si le prix au kilomètre a un sens ici : le proposer sur un ménage
   * ferait régler un tarif qui ne s'appliquerait jamais.
   */
  is_route_service?: boolean;
  taxi_rules?: boolean;
  distance_pricing_enabled?: boolean;
  pickup_fee_cents?: number;
  /** `null` = aucun tarif au kilomètre. À distinguer de `0`, qui facturerait la distance gratuitement. */
  price_per_km_cents?: number | null;
  price_per_minute_cents?: number | null;
  included_km?: number;
}

export interface ZoneCatalog {
  /* La devise VOYAGE avec la zone : un bareme marocain ne s'ecrit pas en euros. */
  zone: { id: number; name: string; country_id: number; currency?: string | null };
  trades: ZoneTrade[];
}
