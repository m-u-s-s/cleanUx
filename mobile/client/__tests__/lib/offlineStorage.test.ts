import AsyncStorage from '@react-native-async-storage/async-storage';
import { offlineCache } from '../../src/lib/offlineStorage';

const CACHE_PREFIX = 'cleanux_cache_';

beforeEach(async () => {
  jest.clearAllMocks();
  await AsyncStorage.clear();
});

describe('offlineCache', () => {
  it('set stores JSON with expiresAt and cachedAt', async () => {
    await offlineCache.set('test-key', { foo: 'bar' }, 60_000);
    const raw = await AsyncStorage.getItem(CACHE_PREFIX + 'test-key');
    expect(raw).not.toBeNull();
    const parsed = JSON.parse(raw!);
    expect(parsed.data).toEqual({ foo: 'bar' });
    expect(parsed.expiresAt).toBeGreaterThan(Date.now() - 1000);
  });

  it('get returns stored value', async () => {
    await offlineCache.set('key1', { value: 42 }, 60_000);
    const result = await offlineCache.get<{ value: number }>('key1');
    expect(result).toEqual({ value: 42 });
  });

  it('get returns null for missing key', async () => {
    const result = await offlineCache.get('nonexistent');
    expect(result).toBeNull();
  });

  it('get returns null and removes expired entries', async () => {
    const entry = { data: 'expired', expiresAt: Date.now() - 1000, cachedAt: Date.now() - 5000 };
    await AsyncStorage.setItem(CACHE_PREFIX + 'exp-key', JSON.stringify(entry));
    const result = await offlineCache.get('exp-key');
    expect(result).toBeNull();
  });

  it('remove deletes the key', async () => {
    await offlineCache.set('to-remove', 'data', 60_000);
    await offlineCache.remove('to-remove');
    expect(await AsyncStorage.getItem(CACHE_PREFIX + 'to-remove')).toBeNull();
  });

  it('clear removes all cache keys but not others', async () => {
    await offlineCache.set('a', 1, 60_000);
    await offlineCache.set('b', 2, 60_000);
    await AsyncStorage.setItem('other_key', 'keep');
    await offlineCache.clear();
    expect(await offlineCache.get('a')).toBeNull();
    expect(await offlineCache.get('b')).toBeNull();
    expect(await AsyncStorage.getItem('other_key')).toBe('keep');
  });
});
