import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { readScanPosition } from '@/tracking';
import { pickImage } from '@/screens/onboarding/documentPicker';

/**
 * LE KIT « SUR PLACE » — état des lieux, imprévus, fil de l'intervention.
 *
 * Le relevé de position accompagne CHAQUE cliché, et il est pris au moment de l'envoi et non au
 * montage de l'écran : une photo prise dans la cave n'a pas la même position que l'entrée, et
 * c'est précisément l'écart qui fait la valeur de la preuve. Son échec n'empêche rien — un
 * sous-sol sans GPS reste un endroit où l'on travaille.
 */

export type MissionMediaType = 'before_photo' | 'after_photo' | 'incident_photo';

export type MissionIncidentType =
  | 'preexisting_damage'
  | 'access_impossible'
  | 'missing_item'
  | 'other';

export interface MissionMediaItem {
  id: number;
  type: MissionMediaType;
  label: string;
  caption: string | null;
  url: string | null;
  taken_at: string | null;
  received_at: string | null;
  lat: number | null;
  lng: number | null;
  accuracy_m: number | null;
  fingerprint: string | null;
}

export interface MissionIncidentItem {
  id: number;
  type: MissionIncidentType;
  label: string;
  severity: string;
  status: string;
  description: string | null;
  reported_at: string | null;
  notified_at: string | null;
  photo: MissionMediaItem | null;
}

/**
 * Un supplément proposé sur place, et où en est la réponse du client.
 *
 * `awaiting_client` est la seule question que pose l'écran : le prestataire a besoin de savoir s'il
 * peut commencer le travail, pas de relire l'historique des statuts.
 */
export interface MissionExtraItem {
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
  approved_at: string | null;
  declined_at: string | null;
  charged_at: string | null;
}

/**
 * La fiche d'accès au lieu — codes, étage, consignes.
 *
 * `available` porte la décision : elle ne s'ouvre qu'une fois l'arrivée confirmée, parce qu'un code
 * d'alarme et l'emplacement d'une boîte à clés sont les clés du domicile de quelqu'un.
 */
export interface MissionAccessSheet {
  available: boolean;
  address: string | null;
  floor: string | null;
  access_instructions: string | null;
  alarm_code_required: boolean;
  access_window: string | null;
  notes: string | null;
  message?: string | null;
}

export interface MissionTimelineEntry {
  kind: 'milestone' | 'checklist' | 'incident' | 'media';
  key: string;
  label: string;
  at: string | null;
  severity?: string;
  met?: boolean;
  media_type?: MissionMediaType;
}

export interface MissionTimeline {
  mission_id: number | null;
  status: string | null;
  started_at: string | null;
  estimated_end_at: string | null;
  progress: { done: number; total: number; percent: number };
  entries: MissionTimelineEntry[];
}

export const INCIDENT_TYPES: { value: MissionIncidentType; label: string }[] = [
  { value: 'preexisting_damage', label: 'Dégât préexistant' },
  { value: 'access_impossible', label: 'Accès impossible' },
  { value: 'missing_item', label: 'Objet ou fourniture manquant' },
  { value: 'other', label: 'Autre imprévu' },
];

export function useMissionMedia(missionId: number | null) {
  return useQuery<MissionMediaItem[]>({
    queryKey: ['provider', 'mission', missionId, 'media'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/media`);

      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

export function useMissionIncidents(missionId: number | null) {
  return useQuery<MissionIncidentItem[]>({
    queryKey: ['provider', 'mission', missionId, 'incidents'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/incidents`);

      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

export function useMissionTimeline(missionId: number | null) {
  return useQuery<MissionTimeline>({
    queryKey: ['provider', 'mission', missionId, 'timeline'],
    queryFn: async () => (await apiClient.get(`/provider/missions/${missionId}/timeline`)).data,
    enabled: missionId !== null,
  });
}

/**
 * Photographie l'état des lieux et l'envoie.
 *
 * `source` distingue l'appareil photo de la galerie. La galerie reste offerte : un prestataire qui
 * vient de photographier hors de l'application n'a pas à recommencer, et lui fermer cette porte le
 * ferait envoyer ses photos par un autre canal, hors de toute trace.
 */
export function useCaptureMissionMedia(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionMediaItem | null,
    ApiError,
    { type: MissionMediaType; source?: 'camera' | 'library'; caption?: string }
  >({
    mutationFn: async ({ type, source = 'camera', caption }) => {
      const picked = await pickImage(source);

      // Annulation : ce n'est pas une erreur, et l'appelant ne doit rien afficher.
      if (!picked) {
        return null;
      }

      const position = await readScanPosition();

      const body = new FormData();
      body.append('type', type);
      // La forme { uri, name, type } est celle qu'attend FormData en React Native pour un fichier
      // local ; un Blob ne fonctionne pas ici.
      body.append('photo', { uri: picked.uri, name: picked.name, type: picked.mimeType } as never);

      if (caption) {
        body.append('caption', caption);
      }

      if (position) {
        body.append('lat', String(position.lat));
        body.append('lng', String(position.lng));

        if (position.accuracy_m !== null) {
          body.append('accuracy_m', String(position.accuracy_m));
        }
      }

      const res = await apiClient.post(`/provider/missions/${missionId}/media`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      return res.data.data;
    },
    onSuccess: (media) => {
      if (media === null) {
        return;
      }

      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'media'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
    },
  });
}

/**
 * Signale un imprévu, photo à l'appui quand il y en a une.
 *
 * La photo est FACULTATIVE : un portail fermé se décrit en une phrase, et exiger un cliché
 * retarderait le seul signalement qui fasse gagner du temps à tout le monde.
 */
export function useReportMissionIncident(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionIncidentItem,
    ApiError,
    { type: MissionIncidentType; description: string; withPhoto?: boolean }
  >({
    mutationFn: async ({ type, description, withPhoto = false }) => {
      const body = new FormData();
      body.append('type', type);
      body.append('description', description);

      if (withPhoto) {
        const picked = await pickImage('camera');

        if (picked) {
          body.append('photo', {
            uri: picked.uri,
            name: picked.name,
            type: picked.mimeType,
          } as never);
        }
      }

      const position = await readScanPosition();

      if (position) {
        body.append('lat', String(position.lat));
        body.append('lng', String(position.lng));
      }

      const res = await apiClient.post(`/provider/missions/${missionId}/incidents`, body, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      return res.data.data;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'incidents'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'media'] });
    },
  });
}

/** Les suppléments de cette mission — le prestataire doit savoir ce qui est accepté avant d'agir. */
export function useMissionExtras(missionId: number | null) {
  return useQuery<MissionExtraItem[]>({
    queryKey: ['provider', 'mission', missionId, 'extras'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/extras`);
      return res.data.data ?? [];
    },
    enabled: missionId !== null,
  });
}

/**
 * PROPOSER UN SUPPLÉMENT CONSTATÉ SUR PLACE.
 *
 * Sans ce geste, le prestataire n'a que deux mauvaises réponses — le faire gratuitement, ou ne pas
 * le faire — et une troisième pire que les deux : s'arranger en espèces, ce qui sort l'argent de la
 * plateforme et le client de toute protection.
 *
 * LE PRIX EST EN CENTIMES jusqu'au serveur. Convertir en euros ici ferait voyager un flottant, et
 * un flottant sur un montant produit des écarts d'un centime que personne ne sait expliquer.
 */
export function useProposeMissionExtra(missionId: number) {
  const qc = useQueryClient();

  return useMutation<
    MissionExtraItem,
    ApiError,
    { label: string; priceCents: number; description?: string }
  >({
    mutationFn: async ({ label, priceCents, description }) => {
      const res = await apiClient.post(`/provider/missions/${missionId}/extras`, {
        label,
        price_cents: priceCents,
        description: description ?? null,
      });

      return res.data.data;
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'extras'] });
      void qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId, 'timeline'] });
    },
  });
}

/** La fiche d'accès de cette mission — verrouillée tant que l'arrivée n'est pas confirmée. */
export function useMissionAccessSheet(missionId: number | null) {
  return useQuery<MissionAccessSheet>({
    queryKey: ['provider', 'mission', missionId, 'access-sheet'],
    queryFn: async () => {
      const res = await apiClient.get(`/provider/missions/${missionId}/access-sheet`);
      return res.data.data;
    },
    enabled: missionId !== null,
  });
}
