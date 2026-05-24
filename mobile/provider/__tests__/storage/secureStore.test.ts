import * as ExpoSecureStore from 'expo-secure-store';
import { secureStore } from '@/storage/secureStore';

jest.mock('expo-secure-store');
const mockStore = ExpoSecureStore as jest.Mocked<typeof ExpoSecureStore>;

describe('secureStore', () => {
  beforeEach(() => jest.clearAllMocks());

  it('getToken returns stored value', async () => {
    mockStore.getItemAsync.mockResolvedValue('test-token-123');
    expect(await secureStore.getToken()).toBe('test-token-123');
    expect(mockStore.getItemAsync).toHaveBeenCalledWith('auth_token');
  });

  it('getToken returns null when no token', async () => {
    mockStore.getItemAsync.mockResolvedValue(null);
    expect(await secureStore.getToken()).toBeNull();
  });

  it('setToken stores value', async () => {
    await secureStore.setToken('new-token');
    expect(mockStore.setItemAsync).toHaveBeenCalledWith('auth_token', 'new-token');
  });

  it('clearToken deletes stored value', async () => {
    await secureStore.clearToken();
    expect(mockStore.deleteItemAsync).toHaveBeenCalledWith('auth_token');
  });

  it('isAuthenticated returns true when token exists', async () => {
    mockStore.getItemAsync.mockResolvedValue('some-token');
    expect(await secureStore.isAuthenticated()).toBe(true);
  });

  it('isAuthenticated returns false when no token', async () => {
    mockStore.getItemAsync.mockResolvedValue(null);
    expect(await secureStore.isAuthenticated()).toBe(false);
  });
});
