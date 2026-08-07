import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '../api/client';

export interface QueuedAction {
  id: string;
  url: string;
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body: unknown;
  createdAt: number;
}

const QUEUE_KEY = 'brio_offline_queue';

/** Sends one queued action; resolves true on success. */
export type ActionSender = (action: QueuedAction) => Promise<boolean>;

/**
 * M16 — default replay goes through apiClient so the request gets the configured baseURL
 * (action.url is relative) AND the Authorization bearer token injected by the interceptor.
 * The previous bare `fetch(action.url, …)` replay always failed: relative URL + no auth.
 */
const defaultSender: ActionSender = async (action) => {
  try {
    await apiClient({ url: action.url, method: action.method, data: action.body });

    return true;
  } catch {
    return false;
  }
};

export const offlineQueue = {
  async enqueue(action: Omit<QueuedAction, 'id' | 'createdAt'>): Promise<void> {
    const queue = await this.getAll();
    queue.push({
      ...action,
      id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
      createdAt: Date.now(),
    });
    await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
  },

  async getAll(): Promise<QueuedAction[]> {
    const raw = await AsyncStorage.getItem(QUEUE_KEY);
    return raw ? JSON.parse(raw) : [];
  },

  async flush(sender: ActionSender = defaultSender): Promise<{ success: number; failed: number }> {
    const queue = await this.getAll();
    let success = 0;
    let failed = 0;
    const remaining: QueuedAction[] = [];

    for (const action of queue) {
      let ok = false;
      try {
        ok = await sender(action);
      } catch {
        ok = false;
      }

      if (ok) {
        success++;
      } else {
        remaining.push(action);
        failed++;
      }
    }

    await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(remaining));
    return { success, failed };
  },

  async clear(): Promise<void> {
    await AsyncStorage.removeItem(QUEUE_KEY);
  },
};
