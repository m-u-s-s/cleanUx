import { renderHook, waitFor } from '@testing-library/react-native';

// Note: préfixées `mock` car babel-plugin-jest-hoist (jest.mock hoisting) interdit de
// référencer depuis la factory une variable hors-scope qui ne commence pas par `mock`
// (convention déjà utilisée ailleurs dans ce repo, ex. `mockNavigate` dans batch-c.test.ts).
const mockRequestForegroundPermissionsAsync = jest.fn();
const mockWatchPositionAsync = jest.fn();

jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: (...a: any[]) => mockRequestForegroundPermissionsAsync(...a),
  watchPositionAsync: (...a: any[]) => mockWatchPositionAsync(...a),
  Accuracy: { High: 4, Balanced: 3 },
}));

import { useGpsWatcher } from '@/tracking/hooks';

describe('useGpsWatcher — état de permission', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockWatchPositionAsync.mockResolvedValue({ remove: jest.fn() });
  });

  it('rapporte "granted" quand la permission est accordée', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('granted'));
    expect(mockWatchPositionAsync).toHaveBeenCalled();
  });

  it('rapporte "denied" et ne démarre aucun suivi quand elle est refusée', async () => {
    mockRequestForegroundPermissionsAsync.mockResolvedValue({ status: 'denied' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('denied'));
    expect(mockWatchPositionAsync).not.toHaveBeenCalled();
  });

  it('reste "pending" quand le hook est désactivé', () => {
    const { result } = renderHook(() => useGpsWatcher(false, jest.fn()));

    expect(result.current.permission).toBe('pending');
    expect(mockRequestForegroundPermissionsAsync).not.toHaveBeenCalled();
  });
});
