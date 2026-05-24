import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { useEffect, useRef } from 'react';
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

export function useGpsWatcher(
  enabled: boolean,
  onPosition: (pos: { latitude: number; longitude: number; speed: number | null; heading: number | null }) => void,
) {
  const subRef = useRef<Location.LocationSubscription | null>(null);

  useEffect(() => {
    if (!enabled) { subRef.current?.remove(); return; }

    (async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') return;

      subRef.current = await Location.watchPositionAsync(
        { accuracy: Location.Accuracy.High, distanceInterval: 10, timeInterval: 5000 },
        (loc) => onPosition({
          latitude: loc.coords.latitude,
          longitude: loc.coords.longitude,
          speed: loc.coords.speed,
          heading: loc.coords.heading,
        }),
      );
    })();

    return () => { subRef.current?.remove(); };
  }, [enabled]);
}
