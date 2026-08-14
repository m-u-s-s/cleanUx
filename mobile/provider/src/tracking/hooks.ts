import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useEffect, useRef, useState } from 'react';
import * as Location from 'expo-location';
import type { ScanPosition } from './scanPosition';

/**
 * La session de suivi telle que le serveur la rend.
 *
 * `route` porte le tracé à afficher : la plateforme savait mesurer une distance, elle ne savait pas
 * dire PAR OÙ l'on passe. C'est ce qui manquait pour que l'écran de trajet ait autre chose qu'un
 * couple de coordonnées à montrer.
 *
 * `destination` change AU COURS d'une course : elle vaut le point de prise en charge pendant
 * l'approche, puis le point de dépose une fois le client à bord. La lire depuis la session — et non
 * depuis la mission — est ce qui fait que la carte suit ce mouvement.
 */
export interface TrackingSession {
  id: number;
  status: string;
  destination?: { lat: number | null; lng: number | null } | null;
  route?: {
    points: { lat: number; lng: number }[];
    source: string | null;
    distance_m: number | null;
  } | null;
}

export function useStartTracking(bookingId: number) {
  return useMutation<TrackingSession, ApiError>({
    mutationFn: async () => (await apiClient.post(`/provider/bookings/${bookingId}/tracking/start`)).data,
  });
}

export function useSendPing(sessionId: number | null) {
  const pingMutation = useMutation<void, ApiError, { latitude: number; longitude: number; speed?: number; heading?: number }>({
    // Le préfixe `/provider` manquait : la route est `/provider/tracking/{session}/ping`, si bien
    // qu'aucun relevé n'a jamais atteint le serveur — le client ne voyait donc jamais bouger son
    // prestataire. Le serveur attend `lat`/`lng`, pas `latitude`/`longitude`.
    mutationFn: async (pos) => {
      await apiClient.post(`/provider/tracking/${sessionId}/ping`, {
        lat: pos.latitude,
        lng: pos.longitude,
        speed_mps: pos.speed,
        heading_deg: pos.heading,
      });
    },
  });
  return pingMutation;
}

/**
 * Le prestataire annonce qu'il commence : la session passe à `in_mission`.
 *
 * La géo-barrière fait passer la session à `arrived` toute seule dès 150 m — c'est une proximité,
 * pas une présence. Ce geste-ci est celui d'un humain qui est réellement devant la porte.
 */
export function useMarkInMission(sessionId: number | null) {
  return useMutation<{ id: number; status: string }, ApiError>({
    mutationFn: async () =>
      (await apiClient.post(`/provider/tracking/${sessionId}/in-mission`)).data?.data,
  });
}

/**
 * « Je suis arrivé » : fait avancer la mission ET la session de suivi.
 *
 * Deux machines à états cohabitent — la mission (`en_route → arrived → started`) et la session
 * de suivi (`enroute → arrived → in_mission`). N'en avancer qu'une laissait le prestataire
 * devant un badge « en route » alors qu'il venait d'annoncer son arrivée.
 *
 * L'arrivée de la mission est SOUPLE : le serveur refuse la transition depuis un état qui ne
 * l'admet pas — mission déjà `arrived`, par exemple, au second passage. Ce refus ne doit pas
 * empêcher la preuve de présence, qui est l'objet du geste.
 *
 * La session, elle, est ouverte si besoin : `startSession` est idempotent par (prestataire,
 * réservation), et le prestataire qui a roulé sans ouvrir l'écran de suivi doit pouvoir annoncer
 * son arrivée quand même.
 */
export function useArriveOnSite(bookingId: number | null, missionId: number | null) {
  const qc = useQueryClient();

  return useMutation<{ id: number; status: string }, ApiError>({
    mutationFn: async () => {
      if (missionId !== null) {
        try {
          await apiClient.post(`/provider/missions/${missionId}/arrive`);
        } catch {
          // Transition déjà faite ou impossible : le suivi prime.
        }
      }

      const session = (await apiClient.post(`/provider/bookings/${bookingId}/tracking/start`)).data?.data;
      const updated = (await apiClient.post(`/provider/tracking/${session.id}/in-mission`)).data?.data;

      return updated ?? session;
    },
    // Sans cette invalidation, le badge resterait sur la valeur mise en cache avant l'appel.
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['provider', 'mission', missionId] });
      qc.invalidateQueries({ queryKey: ['provider', 'missions', 'active'] });
    },
  });
}

/**
 * Valide le code que le client affiche, ce qui atteste des deux appareils au même endroit.
 *
 * La position relevée AU MOMENT DU SCAN accompagne le code, et le serveur la confronte au lieu de
 * l'intervention. Le code seul atteste d'une possession : photographié puis envoyé, ou dicté au
 * téléphone, il se valide depuis n'importe où pendant ses dix minutes de vie.
 *
 * `position` peut être `null` — localisation refusée, relevé impossible. On l'envoie tel quel
 * plutôt que d'inventer une valeur : c'est au serveur de décider si une confirmation sans position
 * est recevable, lui seul n'étant pas sur l'appareil de la personne contrôlée.
 *
 * Le serveur démarre la mission dans la foulée et le dit par `mission_started` — l'appelant en a
 * besoin pour annoncer ce qui s'est réellement passé plutôt que de le supposer : le démarrage est
 * un effet de bord, il peut ne pas avoir lieu sans que la présence en souffre.
 */
export function useConfirmPresence(sessionId: number | null) {
  const qc = useQueryClient();

  return useMutation<
    { id: number; presence_confirmed_at: string | null; mission_started: boolean },
    ApiError,
    { code: string; position: ScanPosition | null }
  >({
    mutationFn: async ({ code, position }) => {
      const body = (await apiClient.post(`/provider/tracking/${sessionId}/confirm-presence`, {
        code,
        ...(position
          ? {
              lat: position.lat,
              lng: position.lng,
              accuracy_m: position.accuracy_m,
              mocked: position.mocked,
            }
          : {}),
      })).data;

      return { ...body?.data, mission_started: body?.mission_started === true };
    },
    // La mission vient de changer d'état : sans invalidation, l'écran précédent afficherait
    // encore « Sur place » alors que l'intervention a démarré.
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['provider', 'mission'] });
      qc.invalidateQueries({ queryKey: ['provider', 'missions', 'active'] });
    },
  });
}

/**
 * Clôture la mission avec le code que le client affiche.
 *
 * Symétrique du scan de présence, à l'autre bout de la visite — mais l'enjeu est plus lourd :
 * la clôture encaisse le paiement pré-autorisé. Un code refusé ne doit donc rien déclencher, et
 * c'est le serveur qui l'établit.
 *
 * La position relevée AU MOMENT DU SCAN accompagne le code, pour la même raison qu'à l'arrivée :
 * le code atteste d'une possession, et un code de fin photographié ou dicté permettrait de
 * facturer une intervention quittée depuis longtemps.
 */
export function useCompleteByQr(missionId: number | null) {
  const qc = useQueryClient();

  return useMutation<
    { mission_id: number; status: string },
    ApiError,
    { code: string; position: ScanPosition | null }
  >({
    mutationFn: async ({ code, position }) =>
      (await apiClient.post(`/provider/missions/${missionId}/complete-by-qr`, {
        code,
        ...(position
          ? {
              lat: position.lat,
              lng: position.lng,
              accuracy_m: position.accuracy_m,
              mocked: position.mocked,
            }
          : {}),
      })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['provider', 'mission'] });
      qc.invalidateQueries({ queryKey: ['provider', 'missions', 'active'] });
    },
  });
}

export function usePushPosition(missionId: number) {
  return useMutation<void, ApiError, { latitude: number; longitude: number; speed?: number }>({
    mutationFn: async (pos) => { await apiClient.post(`/provider/missions/${missionId}/live/position`, pos); },
  });
}

export function usePushEta(missionId: number) {
  return useMutation<void, ApiError, { eta_minutes: number; distance_km: number }>({
    mutationFn: async (eta) => { await apiClient.post(`/provider/missions/${missionId}/live/eta`, eta); },
  });
}

export type GpsPermission = 'pending' | 'granted' | 'denied';

export function useGpsWatcher(
  enabled: boolean,
  onPosition: (pos: { latitude: number; longitude: number; speed: number | null; heading: number | null }) => void,
): { permission: GpsPermission } {
  const subRef = useRef<Location.LocationSubscription | null>(null);
  // Un refus était jusqu'ici avalé par un `return` nu : l'appelant ne pouvait pas l'expliquer
  // à l'utilisateur. La carte du dashboard en a besoin pour justifier l'absence de position.
  const [permission, setPermission] = useState<GpsPermission>('pending');

  useEffect(() => {
    if (!enabled) { subRef.current?.remove(); return; }

    let cancelled = false;

    (async () => {
      try {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (cancelled) return;

        if (status !== 'granted') {
          setPermission('denied');
          return;
        }
        setPermission('granted');

        const sub = await Location.watchPositionAsync(
          { accuracy: Location.Accuracy.High, distanceInterval: 10, timeInterval: 5000 },
          (loc) => onPosition({
            latitude: loc.coords.latitude,
            longitude: loc.coords.longitude,
            speed: loc.coords.speed,
            heading: loc.coords.heading,
          }),
        );

        // Le nettoyage a pu s'exécuter pendant l'await : sans cette seconde vérification,
        // l'abonnement natif serait publié après coup et plus personne ne pourrait l'arrêter.
        if (cancelled) {
          sub.remove();
          return;
        }

        subRef.current = sub;
      } catch {
        // Un ÉCHEC n'est pas un refus, mais l'utilisateur doit quand même comprendre pourquoi il
        // ne se voit pas sur la carte. Sans ce catch, un rejet (services de localisation coupés,
        // module natif absent) partait en promesse non gérée et `permission` restait 'pending'
        // pour toujours : ni position, ni notice — juste le repli pays, sans explication.
        // 'denied' est le seul état que l'UI sait expliquer, on l'utilise donc comme repli.
        if (!cancelled) setPermission('denied');
      }
    })();

    return () => { cancelled = true; subRef.current?.remove(); };
  }, [enabled]);

  return { permission };
}
