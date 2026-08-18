import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../api';

/**
 * L'APPEL MASQUÉ — un numéro relais, jamais celui de l'autre.
 *
 * ── IL EXISTAIT, ET PERSONNE NE POUVAIT L'ATTEINDRE ──────────────────────────────────────────
 *
 * Le service, le fournisseur abstrait, la table, les deux routes : tout était livré et appelé de
 * NULLE PART — ni mobile, ni web. Même famille de défaut que le SOS et le renfort : du travail fait
 * que personne ne peut utiliser.
 *
 * ── CE QU'IL PROTÈGE ─────────────────────────────────────────────────────────────────────────
 *
 * Les deux personnes se joignent sans échanger leur numéro. C'est un service rendu — et c'est aussi
 * ce qui garde le contact SUR la plateforme : un prestataire qui ne connaît pas le numéro de son
 * client ne peut pas lui proposer un arrangement en liquide.
 */
export interface LigneMasquee {
  available: boolean;
  /** Le SEUL numéro réel de cette réponse, et il appartient à la plateforme. */
  proxy_number: string | null;
  /** Assez pour reconnaître son interlocuteur, jamais assez pour le rappeler ailleurs. */
  masked_peer_number: string | null;
  expires_at: string | null;
  message: string | null;
}

/** Côté client : par quel numéro joindre le prestataire de cette réservation. */
export function useLigneMasqueeClient(bookingId: number | null) {
  return useQuery<LigneMasquee>({
    queryKey: ['client', 'booking', bookingId, 'masked-call'],
    queryFn: async () => (await apiClient.get(`/client/bookings/${bookingId}/masked-call`)).data.data,
    enabled: bookingId !== null,
  });
}

/** Côté prestataire : par quel numéro joindre le client de cette mission. */
export function useLigneMasqueePrestataire(missionId: number | null) {
  return useQuery<LigneMasquee>({
    queryKey: ['provider', 'mission', missionId, 'masked-call'],
    queryFn: async () => (await apiClient.get(`/provider/missions/${missionId}/masked-call`)).data.data,
    enabled: missionId !== null,
  });
}
