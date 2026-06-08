import AsyncStorage from '@react-native-async-storage/async-storage';
import { offlineQueue } from '../../src/lib/offlineQueue';

beforeEach(async () => {
  jest.clearAllMocks();
  await AsyncStorage.clear();
});

describe('offlineQueue', () => {
  it('enqueue adds an action with id and createdAt', async () => {
    await offlineQueue.enqueue({ url: '/api/test', method: 'POST', body: { x: 1 } });
    const queue = await offlineQueue.getAll();
    expect(queue).toHaveLength(1);
    const first = queue[0]!;
    expect(first.url).toBe('/api/test');
    expect(first.method).toBe('POST');
    expect(first.id).toBeTruthy();
  });

  it('enqueue appends to existing queue', async () => {
    await offlineQueue.enqueue({ url: '/api/a', method: 'POST', body: {} });
    await offlineQueue.enqueue({ url: '/api/b', method: 'PUT', body: {} });
    const queue = await offlineQueue.getAll();
    expect(queue).toHaveLength(2);
  });

  it('getAll returns empty array when queue is empty', async () => {
    const queue = await offlineQueue.getAll();
    expect(queue).toEqual([]);
  });

  it('flush calls the sender with the action and clears successful actions', async () => {
    await offlineQueue.enqueue({ url: '/api/ok', method: 'POST', body: {} });
    const sender = jest.fn().mockResolvedValue(true);
    const { success, failed } = await offlineQueue.flush(sender as any);
    expect(sender).toHaveBeenCalledWith(expect.objectContaining({ url: '/api/ok', method: 'POST' }));
    expect(success).toBe(1);
    expect(failed).toBe(0);
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  it('flush keeps failed actions in queue', async () => {
    await offlineQueue.enqueue({ url: '/api/fail', method: 'POST', body: {} });
    const sender = jest.fn().mockResolvedValue(false);
    const { success, failed } = await offlineQueue.flush(sender as any);
    expect(success).toBe(0);
    expect(failed).toBe(1);
    expect(await offlineQueue.getAll()).toHaveLength(1);
  });

  it('flush handles sender errors gracefully', async () => {
    await offlineQueue.enqueue({ url: '/api/err', method: 'DELETE', body: null });
    const sender = jest.fn().mockRejectedValue(new Error('Network error'));
    const { success, failed } = await offlineQueue.flush(sender as any);
    expect(success).toBe(0);
    expect(failed).toBe(1);
  });

  it('clear empties the queue', async () => {
    await offlineQueue.enqueue({ url: '/api/x', method: 'POST', body: {} });
    await offlineQueue.clear();
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });
});
