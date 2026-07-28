import { useCallback, useEffect } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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

/**
 * Entrée de cache UNIQUE du statut de présence. Le dashboard carte-first monte deux
 * consommateurs simultanés — `PresencePill` sur la carte et `PresenceToggle` dans le sheet,
 * que gorhom ne démonte jamais. Avec un `useState` par appel de hook, ils tenaient deux états
 * indépendants : changer de statut dans le sheet ne mettait pas à jour la pilule (seul
 * indicateur de la page), et chacun hydratait puis battait dans son coin.
 */
export const PRESENCE_QUERY_KEY = ['provider', 'presence'] as const;

const DEFAULT_STATUS: PresenceStatus = 'offline';

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

/**
 * Hydrate depuis le serveur plutôt que de supposer 'offline' : le prestataire peut être resté
 * en ligne d'une session précédente (c'est le cron de heartbeat périmé qui clôt une session,
 * pas la sortie de l'app). Tous les appelants partagent la même entrée de cache, donc une
 * seule requête part quel que soit le nombre de composants montés.
 */
function usePresenceStatusQuery() {
  return useQuery<PresenceStatus>({
    queryKey: PRESENCE_QUERY_KEY,
    queryFn: async () => {
      const res = await apiClient.get<PresenceResponse>(STATUS_ENDPOINT);
      return res.data?.data?.status ?? DEFAULT_STATUS;
    },
    // Non bloquant : si l'hydratation échoue, `data` reste indéfini, on retombe sur 'offline'
    // et l'utilisateur peut quand même agir. Inutile d'insister sur une lecture de statut.
    retry: false,
  });
}

export function usePresence() {
  const queryClient = useQueryClient();
  const { data } = usePresenceStatusQuery();
  const status = data ?? DEFAULT_STATUS;

  const mutation = useMutation<PresenceStatus, ApiError, PresenceStatus>({
    mutationFn: async (next) => {
      const res = await apiClient.post<PresenceResponse>(TRANSITION_ENDPOINT[next]);
      // Trust the server's answer over the requested value — BookingObserver can hold the
      // provider in 'busy' while a mission is running.
      return res.data?.data?.status ?? next;
    },
    // `setQueryData` plutôt qu'une invalidation : la réponse de la transition FAIT autorité
    // (c'est elle qui porte l'arbitrage serveur), une invalidation rejouerait un GET pour
    // réapprendre ce qu'on vient d'apprendre.
    onSuccess: (confirmed) => queryClient.setQueryData(PRESENCE_QUERY_KEY, confirmed),
  });

  const { mutateAsync } = mutation;

  const setPresenceStatus = useCallback(
    async (next: PresenceStatus) => {
      try {
        await mutateAsync(next);
      } catch {
        // Jamais de rejet vers l'appelant : l'ancien code laissait un « Uncaught (in promise) »
        // à chaque tap en échec. La raison est exposée via `error` ci-dessous.
      }
    },
    [mutateAsync],
  );

  const goOnline = useCallback(() => setPresenceStatus('online'), [setPresenceStatus]);

  return {
    status,
    // L'erreur reste locale au composant qui a déclenché l'écriture : c'est lui qui l'affiche.
    // Seul le statut est partagé.
    error: mutation.error ? humanizeError(mutation.error) : null,
    isPending: mutation.isPending,
    goOnline,
    setPresenceStatus,
  };
}

/**
 * Battement de cœur — À MONTER EN UN SEUL ENDROIT (aujourd'hui `TabNavigator`, rendu une fois
 * par session authentifiée et jamais démonté tant que le prestataire est connecté).
 *
 * Il vivait auparavant dans `usePresence()`, donc chaque composant qui affichait le statut
 * ouvrait son propre intervalle de 30 s : le dashboard en faisait tourner deux pour un seul
 * prestataire. Le sortir du hook de lecture est le seul moyen de garantir un intervalle unique,
 * quel que soit le nombre de consommateurs — un `refetchInterval` sur la requête de statut
 * aurait redonné un minuteur par observateur, et le heartbeat n'est de toute façon pas le même
 * endpoint que la lecture du statut.
 */
export function usePresenceHeartbeat(): void {
  const { data } = usePresenceStatusQuery();
  const isActive = (data ?? DEFAULT_STATUS) !== 'offline';

  useEffect(() => {
    if (!isActive) return;

    const interval = setInterval(() => {
      // Heartbeat carries no status; position updates come from the background GPS task.
      apiClient.post(HEARTBEAT_ENDPOINT).catch(() => {
        // A failed ping is not actionable for the user; the stale scan will flip us offline.
      });
    }, HEARTBEAT_INTERVAL);

    return () => clearInterval(interval);
  }, [isActive]);
}
