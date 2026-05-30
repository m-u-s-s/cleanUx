import { fetchParityMap } from '../useParityMap';
import { apiClient } from '@/api';
import AsyncStorage from '@react-native-async-storage/async-storage';

jest.mock('@/api', () => ({ apiClient: { get: jest.fn() }, ApiError: class extends Error {} }));
jest.mock('@react-native-async-storage/async-storage', () => ({
  setItem: jest.fn(), getItem: jest.fn(),
}));

const MODULES = [{ key: 'help', title: 'Aide', icon: 'help-circle-outline', path: '/help', mobile: 'webview' }];

describe('fetchParityMap', () => {
  beforeEach(() => jest.clearAllMocks());

  it('returns network data and caches it', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: MODULES } });

    const result = await fetchParityMap();

    expect(apiClient.get).toHaveBeenCalledWith('/parity-map');
    expect(result).toEqual(MODULES);
    expect(AsyncStorage.setItem).toHaveBeenCalledWith('cleanux_parity_map', JSON.stringify(MODULES));
  });

  it('falls back to cache when the network fails', async () => {
    (apiClient.get as jest.Mock).mockRejectedValue(new Error('offline'));
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(JSON.stringify(MODULES));

    const result = await fetchParityMap();
    expect(result).toEqual(MODULES);
  });

  it('rethrows when network fails and no cache exists', async () => {
    (apiClient.get as jest.Mock).mockRejectedValue(new Error('offline'));
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(null);

    await expect(fetchParityMap()).rejects.toThrow('offline');
  });
});
