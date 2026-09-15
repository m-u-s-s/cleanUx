import { formatCentimes } from '@/format/money';
import type { Traducteur } from '@/i18n';
import type { MetierDuCatalogue } from '@/catalogue';

/**
 * Le plancher annoncé avant devis. Arrondi à l'unité (`formatCentimes(..., 0)`, comme les autres
 * fourchettes de l'application) et TOUJOURS dit hors taxe : la TVA s'ajoute à la commande.
 */
export function libelleDuPrix(metier: MetierDuCatalogue, devise: string, tr: Traducteur): string {
  if (metier.floor_price_cents === null) {
    return tr('catalogue.prix_selon_vos_reponses');
  }

  const montant = formatCentimes(metier.floor_price_cents, devise, 0);

  return metier.hourly
    ? tr('catalogue.des_montant_par_heure', { montant })
    : tr('catalogue.des_montant', { montant });
}
