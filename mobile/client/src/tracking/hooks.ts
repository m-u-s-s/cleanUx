import { useQuery } from '@tanstack/react-query';
import { useState, useEffect } from 'react';
import { apiClient } from '@/api';
import { useChannel } from '@/realtime';
import type { TrackingSession, LivePosition, LiveEta } from './types';

export function useTrackingSession(bookingId: number | null) {
  return useQuery<TrackingSession>({
    queryKey: ['tracking', 'session', bookingId],
    queryFn: async () => (await apiClient.get(`/client/bookings/${bookingId}/tracking`)).data,
    enabled: bookingId !== null,
    refetchInterval: 30000,
  });
}

export function useTrackingTrail(bookingId: number | null) {
  return useQuery<TrackingSession['points']>({
    queryKey: ['tracking', 'trail', bookingId],
    queryFn: async () => {
      const res = await apiClient.get(`/client/bookings/${bookingId}/tracking/trail`);
      return res.data.data ?? res.data;
    },
    enabled: bookingId !== null,
    refetchInterval: 15000,
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
