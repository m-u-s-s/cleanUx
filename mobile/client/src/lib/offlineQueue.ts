import AsyncStorage from '@react-native-async-storage/async-storage';

export interface QueuedAction {
  id: string;
  url: string;
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body: unknown;
  createdAt: number;
}

const QUEUE_KEY = 'cleanux_offline_queue';

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

  async flush(fetchFn: typeof fetch): Promise<{ success: number; failed: number }> {
    const queue = await this.getAll();
    let success = 0;
    let failed = 0;
    const remaining: QueuedAction[] = [];

    for (const action of queue) {
      try {
        const res = await fetchFn(action.url, {
          method: action.method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(action.body),
        });
        if (res.ok) {
          success++;
        } else {
          remaining.push(action);
          failed++;
        }
      } catch {
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
