import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '@/api';

export interface ParityModule {
  key: string;
  title: string;
  icon: string;
  path: string;
  mobile: 'native' | 'webview';
}

const CACHE_KEY = 'brio_parity_map';

/**
 * Fetches the per-user parity map (which modules exist and how each is
 * delivered on mobile). Caches the last successful response so the navigation
 * survives a cold offline launch; on network failure it serves the cache.
 */
export async function fetchParityMap(): Promise<ParityModule[]> {
  try {
    const res = await apiClient.get('/parity-map');
    const data = res.data.data as ParityModule[];
    await AsyncStorage.setItem(CACHE_KEY, JSON.stringify(data));
    return data;
  } catch (err) {
    const cached = await AsyncStorage.getItem(CACHE_KEY);
    if (cached) {
      return JSON.parse(cached) as ParityModule[];
    }
    throw err;
  }
}
