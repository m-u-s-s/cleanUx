import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { useChannel } from '@/realtime';

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

export interface OnSiteTimeline {
  mission_id: number | null;
  status: string | null;
  started_at: string | null;
  estimated_end_at: string | null;
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
export function useLiveOnSite(bookingId: number | null, missionId: number | null) {
  const qc = useQueryClient();

  const rafraichir = () => {
    void qc.invalidateQueries({ queryKey: ['client', 'booking', bookingId, 'onsite'] });
  };

  useChannel(missionId ? `private-mission.${missionId}` : null, {
    'mission.media': rafraichir,
    'mission.incident': rafraichir,
    MissionStatusUpdated: rafraichir,
  });
}
