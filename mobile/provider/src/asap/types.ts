/**
 * Une course immédiate proposée à ce prestataire.
 *
 * La forme suit exactement ce que sert `GET /provider/asap-offers` : le métier, la distance, le
 * montant que le CLIENT a déjà accepté — pas une estimation refaite ici — et depuis combien de
 * temps il attend. C'est tout ce qu'il faut pour décider en trois secondes, et rien de plus.
 */
export interface AsapOffer {
  id: number;
  /** L'identifiant de la DEMANDE, pas de la notification : c'est lui qu'attendent accept/decline. */
  asap_dispatch_request_id: number;
  trade: string | null;
  distance_m: number | null;
  distance_km: number | null;
  estimate_min_cents: number | null;
  estimate_max_cents: number | null;
  notified_at: string | null;
  waiting_seconds: number | null;
}
