import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useEffect, useRef, useState } from 'react';
import * as Location from 'expo-location';

interface TrackingSession { id: number; status: string; }

export function useStartTracking(bookingId: number) {
  return useMutation<TrackingSession, ApiError>({
    mutationFn: async () => (await apiClient.post(`/provider/bookings/${bookingId}/tracking/start`)).data,
  });
}

export function useSendPing(sessionId: number | null) {
  const pingMutation = useMutation<void, ApiError, { latitude: number; longitude: number; speed?: number; heading?: number }>({
    mutationFn: async (pos) => { await apiClient.post(`/tracking/${sessionId}/ping`, pos); },
  });
  return pingMutation;
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
