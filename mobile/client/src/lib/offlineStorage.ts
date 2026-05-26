import AsyncStorage from '@react-native-async-storage/async-storage';

const CACHE_PREFIX = 'cleanux_cache_';

interface CacheEntry<T> {
  data: T;
  expiresAt: number;
  cachedAt: number;
}

export const offlineCache = {
  async get<T>(key: string): Promise<T | null> {
    const raw = await AsyncStorage.getItem(CACHE_PREFIX + key);
    if (!raw) return null;
    const entry: CacheEntry<T> = JSON.parse(raw);
    if (Date.now() > entry.expiresAt) {
      await AsyncStorage.removeItem(CACHE_PREFIX + key);
      return null;
    }
    return entry.data;
  },

  async set<T>(key: string, data: T, ttlMs = 5 * 60 * 1000): Promise<void> {
    const entry: CacheEntry<T> = {
      data,
      expiresAt: Date.now() + ttlMs,
      cachedAt: Date.now(),
    };
    await AsyncStorage.setItem(CACHE_PREFIX + key, JSON.stringify(entry));
  },

  async remove(key: string): Promise<void> {
    await AsyncStorage.removeItem(CACHE_PREFIX + key);
  },

  async clear(): Promise<void> {
    const keys = await AsyncStorage.getAllKeys();
    const cacheKeys = keys.filter((k) => k.startsWith(CACHE_PREFIX));
    if (cacheKeys.length > 0) {
      await AsyncStorage.multiRemove(cacheKeys);
    }
  },
};
