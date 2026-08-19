import NetInfo from '@react-native-community/netinfo';
import { apiClient } from './client';
import { offlineQueue } from '../lib/offlineQueue';
import { ApiError } from './types';

export interface MutationResult {
  queued: boolean;
  response?: unknown;
}

/**
 * Performs a mutating API call with offline-queue fallback.
 *
 * - If the device has no connectivity the action is stored locally and
 *   `{ queued: true }` is returned — the caller can surface a toast.
 * - If the device is online but the request fails due to a network error
 *   (no `error.response`) the action is also queued.
 * - HTTP errors from the server (4xx / 5xx) are rethrown so the caller
 *   can present a meaningful error message.
 */
export async function offlineAwareMutation(
  url: string,
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  body: unknown,
  /*
   * CE QUE LA PERSONNE CROIT AVOIR FAIT.
   *
   * Il voyage avec l'action pour une seule raison : le jour ou le serveur la REFUSE
   * definitivement, il faut pouvoir dire « votre case “vitres” n'a pas ete enregistree » plutot
   * que « une action a echoue ». Sans ce libelle, un abandon est un silence.
   */
  label?: string,
): Promise<MutationResult> {
  const state = await NetInfo.fetch();

  if (!state.isConnected) {
    await offlineQueue.enqueue({ url, method, body, label });
    return { queued: true };
  }

  try {
    const res = await apiClient({ url, method, data: body });
    return { queued: false, response: res.data };
  } catch (error: unknown) {
    // The apiClient response interceptor maps all axios errors to ApiError.
    // An ApiError with status > 0 is a real HTTP error (4xx/5xx) from the server
    // — rethrow so callers can show a meaningful message.
    // An ApiError with status === 0 means a network-level failure (no server
    // response was received) — queue for offline retry.
    if (error instanceof ApiError && error.status > 0) {
      throw error;
    }
    // Fallback: a raw axios error that somehow escaped the interceptor still
    // has a .response property when the server did respond.
    const hasAxiosResponse = error !== null && typeof error === 'object' && 'response' in error;
    if (hasAxiosResponse) {
      throw error;
    }
    await offlineQueue.enqueue({ url, method, body, label });
    return { queued: true };
  }
}
