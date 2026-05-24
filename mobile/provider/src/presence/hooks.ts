import { useState, useEffect, useCallback, useRef } from 'react';
import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type { PresenceStatus } from './types';

const HEARTBEAT_INTERVAL = 30000; // 30s

export function usePresence() {
  const [status, setStatus] = useState<PresenceStatus>('offline');
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const heartbeat = useMutation<void, ApiError>({
    mutationFn: () => apiClient.post('/provider/presence-v2/heartbeat', { status }),
  });

  const goOnline = useCallback(async () => {
    await apiClient.post('/provider/presence/online');
    setStatus('online');
  }, []);

  const setPresenceStatus = useCallback(async (newStatus: PresenceStatus) => {
    if (newStatus === 'offline') {
      if (intervalRef.current) clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    await apiClient.post('/provider/presence-v2/heartbeat', { status: newStatus });
    setStatus(newStatus);
  }, []);

  useEffect(() => {
    if (status !== 'offline') {
      intervalRef.current = setInterval(() => heartbeat.mutate(), HEARTBEAT_INTERVAL);
      return () => {
        if (intervalRef.current) clearInterval(intervalRef.current);
      };
    }
  }, [status]);

  return { status, goOnline, setPresenceStatus };
}
