import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useChannel } from '@/realtime';
import type { MissionClock } from '@brio/shared';

/**
 * CE QUI SE PASSE CHEZ MOI — le suivi qui continue après la sonnette.
 *
 * Le suivi existant s'arrête à la porte : la carte montre le trajet, puis plus rien pendant les
 * deux heures qui suivent. Ces trois lectures couvrent l'intervention elle-même.
 *
 * Le client raisonne en RÉSERVATION : c'est le seul identifiant que son application connaisse. Le
 * `mission_id` lui est RENDU par le fil, et sert ensuite à écouter le bon canal — c'est aussi
 * ainsi qu'on répare l'abonnement temps réel, qui s'inscrivait jusqu'ici à `mission.{bookingId}`,
 * soit la mission de quelqu'un d'autre quand les deux compteurs divergent.
 */

export type MissionMediaType = 'before_photo' | 'after_photo' | 'incident_photo';

export interface OnSiteMedia {
  id: number;
  type: MissionMediaType;
  label: string;
  caption: string | null;
  url: string | null;
  taken_at: string | null;
  fingerprint: string | null;
}

export interface OnSiteTimelineEntry {
  kind: 'milestone' | 'checklist' | 'incident' | 'media';
  key: string;
  label: string;
  at: string | null;
  severity?: string;
  met?: boolean;
  media_type?: MissionMediaType;
}

/** Ce que le serveur autorise en matière de prolongation, à cet instant. */
export interface OnSiteExtension {
  allowed: boolean;
  /** La phrase à MONTRER quand `allowed` est faux. Écrite pour le client, pas pour le journal. */
  reason: string | null;
  /** Ce qu'il reste d'achetable avant le plafond de la prestation. */
  max_minutes: number;
  /** Le pas d'achat — le même qu'à la commande, décidé par la configuration du serveur. */
  increment_minutes: number;
  /**
   * LES CHOIX, AVEC LEUR PRIX, CALCULÉS PAR LE SERVEUR.
   *
   * L'écran n'a aucune multiplication à faire. Le client décide sur ce montant : le fabriquer ici
   * créerait un second prix pour la même prestation, et c'est celui de l'appareil qu'il aurait lu
   * au moment de dire oui.
   */
  options: Array<{ minutes: number; label: string; amount_cents: number | null }>;
}

export interface OnSiteTimeline {
  mission_id: number | null;
  status: string | null;
  started_at: string | null;
  /**
   * UNE PRÉVISION, sans conséquence : le démarrage réel plus la durée du devis.
   *
   * À ne pas confondre avec `clock.deadline_at`, qui est un ENGAGEMENT — l'instant où le temps
   * acheté s'épuise et au-delà duquel le dépassement se facture.
   */
  estimated_end_at: string | null;
  /** Le compteur des prestations vendues au temps. `applies: false` partout ailleurs. */
  clock?: MissionClock | null;
  /**
   * Peut-on encore prolonger, et jusqu'où.
   *
   * `null` sur tout ce qui n'est pas vendu au temps — le bouton n'existe alors pas. L'écran doit
   * le savoir AVANT que le client appuie : un bouton qu'on découvre inactif en appuyant dessus est
   * un bouton cassé.
   */
  extension?: OnSiteExtension | null;
  progress: { done: number; total: number; percent: number };
  entries: OnSiteTimelineEntry[];
}

export interface OnSiteIncident {
  id: number;
  type: string;
  label: string;
  severity: string;
  status: string;
  description: string | null;
  reported_at: string | null;
  photo: OnSiteMedia | null;
  dispute_prefill: { category: string; subject: string; description: string };
}

export function useOnSiteTimeline(bookingId: number | null) {
  return useQuery<OnSiteTimeline>({
    queryKey: ['client', 'booking', bookingId, 'onsite', 'timeline'],
    queryFn: async () =>
      (await apiClient.get(`/client/bookings/${bookingId}/onsite/timeline`)).data,
    enabled: bookingId !== null,
    /*
     * Le temps réel rafraîchit à l'événement ; ce sondage lent est le FILET. La socket tombe dans
     * un ascenseur et le client, lui, reste devant son écran à se demander si quelque chose avance.
     */
    refetchInterval: 60000,
  });
}

export function useOnSiteMedia(bookingId: number | null) {
  return useQuery<{ before: OnSiteMedia[]; after: OnSiteMedia[]; incident: OnSiteMedia[] }>({
    queryKey: ['client', 'booking', bookingId, 'onsite', 'media'],
    queryFn: async () => (await apiClient.get(`/client/bookings/${bookingId}/onsite/media`)).data,
    enabled: bookingId !== null,
  });
}

export function useOnSiteIncidents(bookingId: number | null) {
  return useQuery<OnSiteIncident[]>({
    queryKey: ['client', 'booking', bookingId, 'onsite', 'incidents'],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/onsite/incidents`);

      return res.data.data ?? [];
    },
    enabled: bookingId !== null,
  });
}

/**
 * Rafraîchit le fil dès que quelque chose bouge sur place.
 *
 * Les noms d'événements sont ceux que le serveur DIFFUSE (`broadcastAs`), pas les noms de classes
 * PHP. La confusion entre les deux avait déjà rendu la messagerie muette ; elle vivait encore ici.
 */
/** Un supplément proposé sur place, du point de vue du client qui doit répondre. */
export interface OnSiteExtra {
  id: number;
  label: string;
  description: string | null;
  price_cents: number;
  price: number;
  currency: string;
  status: 'proposed' | 'approved' | 'declined' | 'charged';
  awaiting_client: boolean;
  proposed_by: string | null;
  proposed_at: string | null;
}

export function useOnSiteExtras(bookingId: number | null) {
  return useQuery<OnSiteExtra[]>({
    queryKey: ['client', 'booking', bookingId, 'onsite', 'extras'],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/onsite/extras`);
      return res.data.data ?? [];
    },
    enabled: bookingId !== null,
  });
}

/**
 * RÉPONDRE EN UN GESTE.
 *
 * Le prestataire est chez le client, à l'instant : une réponse qui arrive après son départ ne sert
 * plus à rien. Pas de formulaire, pas de confirmation en deux temps — un bouton, une décision.
 *
 * Le refus emprunte le MÊME chemin que l'accord : c'est une réponse, pas un abandon, et le
 * prestataire doit l'apprendre aussi vite que l'accord pour ne pas attendre pour rien.
 */
export function useRepondreAuSupplement(bookingId: number) {
  const qc = useQueryClient();

  return useMutation<OnSiteExtra, ApiError, { extraId: number; accepte: boolean }>({
    mutationFn: async ({ extraId, accepte }) => {
      const res = await apiClient.post(
        `/client/bookings/${bookingId}/onsite/extras/${extraId}/${accepte ? 'approve' : 'decline'}`,
      );

      return res.data.data;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['client', 'booking', bookingId, 'onsite'] });
    },
  });
}

/**
 * PROLONGER — acheter du temps en plus, au tarif normal.
 *
 * LE MONTANT N'EST PAS CALCULÉ ICI, et l'écran n'en propose aucun : le serveur répond avec
 * l'horloge à jour, qui porte le nouveau temps acheté et le nouveau montant. Une application qui
 * multiplierait elle-même un tarif par une durée créerait un second prix pour la même prestation
 * — et c'est le sien que le client aurait lu avant de confirmer.
 *
 * LE REFUS EST UNE RÉPONSE. Le serveur renvoie 422 avec une phrase lisible — « le temps
 * supplémentaire est déjà en cours de facturation », « la durée maximale est atteinte » — et c'est
 * cette phrase qui doit être montrée, pas un « une erreur est survenue » générique.
 */
export function useProlongerLesHeures(bookingId: number | null) {
  const qc = useQueryClient();

  return useMutation<{ clock: MissionClock; extension: OnSiteExtension | null }, ApiError, number>({
    mutationFn: async (additionalMinutes) =>
      (await apiClient.post(`/client/bookings/${bookingId}/onsite/extend`, {
        additional_minutes: additionalMinutes,
      })).data,
    onSuccess: () => {
      // Le fil porte l'horloge ET l'état de la prolongation : les deux ont changé.
      void qc.invalidateQueries({ queryKey: ['client', 'booking', bookingId, 'onsite'] });
    },
  });
}

export function useLiveOnSite(bookingId: number | null, missionId: number | null) {
  const qc = useQueryClient();

  const rafraichir = () => {
    void qc.invalidateQueries({ queryKey: ['client', 'booking', bookingId, 'onsite'] });
  };

  useChannel(missionId ? `private-mission.${missionId}` : null, {
    'mission.media': rafraichir,
    'mission.incident': rafraichir,
    // Un supplément proposé doit APPARAÎTRE : demander une réponse en un tap suppose d'abord que
    // la personne voie qu'on lui demande quelque chose.
    'mission.extra': rafraichir,
    MissionStatusUpdated: rafraichir,
  });
}
