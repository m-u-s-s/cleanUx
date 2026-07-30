import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { apiClient } from '@/api';
import { useChannel } from '@/realtime';
import type {
  ApiTrackingPoint,
  ApiTrackingSession,
  LiveEta,
  LivePosition,
  CompletionCode,
  PresenceCode,
  TrackingPoint,
  TrackingSession,
} from './types';

/**
 * Point de traduction unique entre le vocabulaire du serveur et celui des cartes.
 *
 * Rien ne traduisait auparavant : les crochets renvoyaient la charge brute, et les écrans y
 * cherchaient des champs qui n'y figuraient pas. `useTrackingSession` renvoyait même l'enveloppe
 * `{ data: … }` entière, si bien que `session.status` et `session.eta_minutes` valaient
 * systématiquement `undefined`.
 */
function toPosition(lat: number | null, lng: number | null, speed?: number | null): LivePosition | null {
  // Une coordonnée nulle n'est pas une position à zéro : sans les deux, il n'y a rien à situer.
  if (lat === null || lat === undefined || lng === null || lng === undefined) {
    return null;
  }

  return {
    latitude: Number(lat),
    longitude: Number(lng),
    ...(speed === null || speed === undefined ? {} : { speed: Number(speed) }),
  };
}

function toSession(raw: ApiTrackingSession): TrackingSession {
  return {
    code: raw.code,
    status: raw.status,
    destination: toPosition(raw.destination?.lat ?? null, raw.destination?.lng ?? null),
    provider: toPosition(raw.provider?.lat ?? null, raw.provider?.lng ?? null, raw.provider?.speed_mps),
    eta_seconds: raw.eta_seconds ?? null,
    eta_minutes: raw.eta_minutes ?? null,
    arrived_at: raw.arrived_at ?? null,
    in_mission_at: raw.in_mission_at ?? null,
    last_ping_at: raw.last_ping_at ?? null,
    presence_confirmed_at: raw.presence_confirmed_at ?? null,
  };
}

function toPoint(raw: ApiTrackingPoint): TrackingPoint {
  return {
    latitude: Number(raw.lat),
    longitude: Number(raw.lng),
    eta_seconds: raw.eta_seconds ?? null,
    distance_to_dest_m: raw.distance_to_dest_m ?? null,
    recorded_at: raw.at,
  };
}

/**
 * Le serveur répond `{ data: null }` tant qu'aucune session n'est ouverte — une réservation
 * confirmée mais non démarrée, par exemple. C'est une absence légitime, pas une erreur.
 */
export function useTrackingSession(bookingId: number | null) {
  return useQuery<TrackingSession | null>({
    queryKey: ['tracking', 'session', bookingId],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/tracking`);
      const raw = res.data?.data ?? null;

      return raw ? toSession(raw as ApiTrackingSession) : null;
    },
    enabled: bookingId !== null,
    refetchInterval: 30000,
  });
}

export function useTrackingTrail(bookingId: number | null) {
  return useQuery<TrackingPoint[]>({
    queryKey: ['tracking', 'trail', bookingId],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/tracking/trail`);
      const raw = res.data?.data ?? res.data;

      return Array.isArray(raw) ? (raw as ApiTrackingPoint[]).map(toPoint) : [];
    },
    enabled: bookingId !== null,
    refetchInterval: 15000,
  });
}

/**
 * Demande le code de présence à afficher.
 *
 * Une mutation, pas une requête : chaque appel forge un code neuf et périme le précédent. Une
 * interrogation périodique le remplacerait sous le nez du prestataire en train de le scanner.
 */
export function usePresenceCode(bookingId: number | null) {
  return useMutation<PresenceCode, Error>({
    mutationFn: async () => {
      const res = await apiClient.post(`/client/bookings/${bookingId}/presence-code`);

      return (res.data?.data ?? res.data) as PresenceCode;
    },
  });
}

/**
 * Code de fin, à afficher quand le travail est terminé.
 *
 * Même direction que le code de présence — le client atteste, le prestataire scanne — mais
 * l'enjeu est plus lourd : la clôture encaisse le paiement pré-autorisé. Le montrer doit donc
 * rester un geste délibéré du client.
 */
export function useCompletionCode(bookingId: number | null) {
  return useMutation<CompletionCode, Error>({
    mutationFn: async () => {
      const res = await apiClient.post(`/client/bookings/${bookingId}/completion-code`);

      return (res.data?.data ?? res.data) as CompletionCode;
    },
  });
}

export function useLiveTracking(missionId: number | null) {
  const [position, setPosition] = useState<LivePosition | null>(null);
  const [eta, setEta] = useState<LiveEta | null>(null);

  useChannel(
    missionId ? `private-mission.${missionId}` : null,
    {
      'MissionLivePosition': (data: unknown) => setPosition(data as LivePosition),
      'MissionLiveEta': (data: unknown) => setEta(data as LiveEta),
      'MissionStatusUpdated': () => {},
    },
  );

  return { position, eta };
}
