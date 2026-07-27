import { useState, useEffect, useCallback, useRef } from 'react';
import { apiClient, ApiError } from '@/api';
import type { PresenceStatus } from './types';

const HEARTBEAT_INTERVAL = 30000; // 30s

/**
 * Presence v2 exposes **one endpoint per transition** — the target status is not a body
 * field. The previous implementation posted `{ status }` to /presence-v2/heartbeat, which
 * the controller silently ignores (it only validates lat/lng), so no status ever changed;
 * and because heartbeat is refused while the provider is offline, every tap answered 422.
 * Going online hit the legacy Phase 11 route /provider/presence/online, which additionally
 * requires a provider_profiles row (403) and required lat+lng (422 on an empty body).
 */
const TRANSITION_ENDPOINT: Record<PresenceStatus, string> = {
  online: '/provider/presence-v2/online',
  busy: '/provider/presence-v2/busy',
  on_break: '/provider/presence-v2/break',
  offline: '/provider/presence-v2/offline',
};

const STATUS_ENDPOINT = '/provider/presence-v2';
const HEARTBEAT_ENDPOINT = '/provider/presence-v2/heartbeat';

type PresenceResponse = {
  data?: {
    status?: PresenceStatus;
    is_active?: boolean;
  };
};

/**
 * axios' own "Request failed with status code 422" is what the user used to see. Prefer the
 * server's field errors (the v2 controller puts its reason in `errors.status`), then a real
 * message, and only then a generic French fallback.
 */
function humanizeError(e: unknown): string {
  const fallback = 'Changement de statut impossible. Réessaie.';
  if (!(e instanceof ApiError)) return fallback;

  const firstFieldError = Object.values(e.errors ?? {})[0]?.[0];
  if (firstFieldError) return firstFieldError;

  const isAxiosGeneric = /^Request failed with status code/.test(e.message ?? '');
  if (e.message && !isAxiosGeneric) return e.message;

  return fallback;
}

export function usePresence() {
  const [status, setStatus] = useState<PresenceStatus>('offline');
  const [error, setError] = useState<string | null>(null);
  const [isPending, setIsPending] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Hydrate from the server instead of assuming 'offline': the provider may still be online
  // from a previous session (the stale-heartbeat cron is what ends a session, not app exit).
  useEffect(() => {
    let cancelled = false;

    apiClient
      .get<PresenceResponse>(STATUS_ENDPOINT)
      .then(res => {
        const remote = res.data?.data?.status;
        if (!cancelled && remote) setStatus(remote);
      })
      .catch(() => {
        // Non-blocking: keep the local 'offline' default and let the user act.
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const setPresenceStatus = useCallback(async (next: PresenceStatus) => {
    setError(null);
    setIsPending(true);

    try {
      const res = await apiClient.post<PresenceResponse>(TRANSITION_ENDPOINT[next]);
      // Trust the server's answer over the requested value — BookingObserver can hold the
      // provider in 'busy' while a mission is running.
      setStatus(res.data?.data?.status ?? next);
    } catch (e) {
      setError(humanizeError(e));
    } finally {
      setIsPending(false);
    }
  }, []);

  const goOnline = useCallback(() => setPresenceStatus('online'), [setPresenceStatus]);

  useEffect(() => {
    if (status === 'offline') return;

    intervalRef.current = setInterval(() => {
      // Heartbeat carries no status; position updates come from the background GPS task.
      apiClient.post(HEARTBEAT_ENDPOINT).catch(() => {
        // A failed ping is not actionable for the user; the stale scan will flip us offline.
      });
    }, HEARTBEAT_INTERVAL);

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
      intervalRef.current = null;
    };
  }, [status]);

  return { status, error, isPending, goOnline, setPresenceStatus };
}
