import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';

export interface CatalogTrade {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  /** Les zones où ce métier est effectivement vendu. */
  zone_ids: number[];
  allows_asap: boolean;
}

export interface CatalogSector {
  id: number;
  name: string;
  slug: string;
  trades: CatalogTrade[];
}

export interface CatalogZone {
  id: number;
  name: string;
  slug?: string;
  code?: string | null;
}

export interface RegistrationOptions {
  sectors: CatalogSector[];
  zones: CatalogZone[];
}

/**
 * CE QUE LA PLATEFORME VEND, ET OÙ — une seule source pour l'inscription et le réglage.
 *
 * `GET /api/trades` rendait la table `trades` telle quelle : tous les métiers ACTIFS, y compris
 * ceux qu'aucune zone n'ouvre. Un prestataire pouvait donc s'inscrire sur un métier que personne
 * ne peut commander, attendre des missions qui ne viendraient jamais, et conclure que la
 * plateforme est vide. `registration-options` ne rend que les couples réellement ouverts au
 * catalogue, et le formulaire web lit déjà celui-là : deux listes construites séparément finissent
 * toujours par diverger, et personne ne sait alors laquelle dit vrai.
 *
 * PUBLIC, ET IL LE FAUT : il est appelé avant que le compte existe, donc avant tout jeton.
 */
export function useRegistrationOptions(country = 'BE') {
  return useQuery<RegistrationOptions>({
    queryKey: ['catalog', 'registration-options', country],
    queryFn: async () =>
      (await apiClient.get(`/catalog/registration-options?country=${encodeURIComponent(country)}`))
        .data.data,
    // Le catalogue bouge à la journée, pas à la seconde.
    staleTime: 5 * 60 * 1000,
  });
}

/** Les métiers de tous les secteurs, à plat — pour les écrans qui ne groupent pas. */
export function flattenTrades(options?: RegistrationOptions): CatalogTrade[] {
  return (options?.sectors ?? []).flatMap((secteur) => secteur.trades);
}

/**
 * Les zones où le métier choisi est vendu.
 *
 * Sans ce filtre, l'écran laisse déclarer « plomberie à Bastogne » alors que la plomberie n'y est
 * pas ouverte : la couverture est enregistrée, le prestataire l'a vue à l'écran, et le dispatch ne
 * lui proposera jamais rien. Un choix qui ne peut rien produire ne doit pas être proposé.
 */
export function zonesPourMetier(
  options: RegistrationOptions | undefined,
  tradeId: number | null,
): CatalogZone[] {
  const zones = options?.zones ?? [];

  if (!tradeId) {
    return zones;
  }

  const metier = flattenTrades(options).find((t) => t.id === tradeId);

  if (!metier || metier.zone_ids.length === 0) {
    return zones;
  }

  return zones.filter((zone) => metier.zone_ids.includes(zone.id));
}
