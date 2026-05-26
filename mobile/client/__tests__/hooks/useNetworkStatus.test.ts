import { renderHook, act } from '@testing-library/react-native';

const listeners: Array<(state: { isConnected: boolean | null }) => void> = [];

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn((cb: (state: { isConnected: boolean | null }) => void) => {
    listeners.push(cb);
    return () => {
      const idx = listeners.indexOf(cb);
      if (idx > -1) listeners.splice(idx, 1);
    };
  }),
}));

import { useNetworkStatus } from '../../src/hooks/useNetworkStatus';

beforeEach(() => {
  listeners.length = 0;
  jest.clearAllMocks();
});

describe('useNetworkStatus', () => {
  it('starts online by default', () => {
    const { result } = renderHook(() => useNetworkStatus());
    expect(result.current.isOnline).toBe(true);
  });

  it('updates to offline when NetInfo fires offline event', () => {
    const { result } = renderHook(() => useNetworkStatus());
    act(() => {
      listeners.forEach((cb) => cb({ isConnected: false }));
    });
    expect(result.current.isOnline).toBe(false);
  });

  it('updates back to online when connectivity restored', () => {
    const { result } = renderHook(() => useNetworkStatus());
    act(() => { listeners.forEach((cb) => cb({ isConnected: false })); });
    act(() => { listeners.forEach((cb) => cb({ isConnected: true })); });
    expect(result.current.isOnline).toBe(true);
  });

  it('treats null isConnected as online', () => {
    const { result } = renderHook(() => useNetworkStatus());
    act(() => { listeners.forEach((cb) => cb({ isConnected: null })); });
    expect(result.current.isOnline).toBe(true);
  });
});
