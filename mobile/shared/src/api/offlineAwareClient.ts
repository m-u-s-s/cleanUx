import NetInfo from '@react-native-community/netinfo';
import { apiClient } from './client';
import { offlineQueue } from '../lib/offlineQueue';

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
): Promise<MutationResult> {
  const state = await NetInfo.fetch();

  if (!state.isConnected) {
    await offlineQueue.enqueue({ url, method, body });
    return { queued: true };
  }

  try {
    const res = await apiClient({ url, method, data: body });
    return { queued: false, response: res.data };
  } catch (error: unknown) {
    const hasResponse = error !== null && typeof error === 'object' && 'response' in error;
    if (!hasResponse) {
      await offlineQueue.enqueue({ url, method, body });
      return { queued: true };
    }
    throw error;
  }
}
