/**
 * Unit tests for offlineAwareMutation and useOfflineSync.
 */
import { renderHook, act } from '@testing-library/react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

// ── Mocks ─────────────────────────────────────────────────────────────────────

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

// NetInfo: controlled by per-test overrides via mockNetInfo
const mockFetch = jest.fn().mockResolvedValue({ isConnected: true });
const listeners: Array<(state: { isConnected: boolean | null }) => void> = [];

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn((cb: (state: { isConnected: boolean | null }) => void) => {
    listeners.push(cb);
    return () => {
      const idx = listeners.indexOf(cb);
      if (idx > -1) listeners.splice(idx, 1);
    };
  }),
  fetch: jest.fn(() => mockFetch()),
  default: {
    addEventListener: jest.fn((cb: (state: { isConnected: boolean | null }) => void) => {
      listeners.push(cb);
      return () => {
        const idx = listeners.indexOf(cb);
        if (idx > -1) listeners.splice(idx, 1);
      };
    }),
    fetch: jest.fn(() => mockFetch()),
  },
}));

import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import { apiClient } from '../client';
import { offlineAwareMutation } from '../offlineAwareClient';
import { useOfflineSync } from '../useOfflineSync';
import { offlineQueue } from '../../lib/offlineQueue';

const axiosMock = new MockAdapter(apiClient);

beforeEach(async () => {
  axiosMock.reset();
  jest.clearAllMocks();
  listeners.length = 0;
  mockFetch.mockResolvedValue({ isConnected: true });
  await AsyncStorage.clear();
});

// ── offlineAwareMutation ──────────────────────────────────────────────────────

describe('offlineAwareMutation', () => {
  it('makes request immediately when online and returns response', async () => {
    axiosMock.onPost('/api/test').reply(201, { id: 1 });

    const result = await offlineAwareMutation('/api/test', 'POST', { name: 'x' });

    expect(result.queued).toBe(false);
    expect(result.response).toEqual({ id: 1 });
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  it('queues action when offline (isConnected false)', async () => {
    mockFetch.mockResolvedValueOnce({ isConnected: false });

    const result = await offlineAwareMutation('/api/booking', 'POST', { service: 'cleaning' });

    expect(result.queued).toBe(true);
    const queue = await offlineQueue.getAll();
    expect(queue).toHaveLength(1);
    expect(queue[0]!.url).toBe('/api/booking');
    expect(queue[0]!.method).toBe('POST');
  });

  it('queues action when online but network error occurs', async () => {
    axiosMock.onPost('/api/booking').networkError();

    const result = await offlineAwareMutation('/api/booking', 'POST', { service: 'cleaning' });

    expect(result.queued).toBe(true);
    const queue = await offlineQueue.getAll();
    expect(queue).toHaveLength(1);
  });

  it('throws HTTP errors (4xx/5xx) — does NOT queue', async () => {
    axiosMock.onPost('/api/booking').reply(422, {
      ok: false,
      error_code: 'validation_failed',
      message: 'Invalid input',
    });

    await expect(offlineAwareMutation('/api/booking', 'POST', {})).rejects.toBeDefined();

    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  it('supports PUT, PATCH, DELETE methods', async () => {
    axiosMock.onPut('/api/profile').reply(200, { ok: true });

    const result = await offlineAwareMutation('/api/profile', 'PUT', { name: 'Bob' });

    expect(result.queued).toBe(false);
    expect(axiosMock.history['put']).toHaveLength(1);
  });
});

// ── useOfflineSync ────────────────────────────────────────────────────────────

describe('useOfflineSync', () => {
  it('mounts and unmounts without errors', () => {
    const { unmount } = renderHook(() => useOfflineSync());
    expect(() => unmount()).not.toThrow();
  });

  it('flushes queue via apiClient when NetInfo fires connected=true event', async () => {
    // Pre-load a queued action
    await offlineQueue.enqueue({ url: '/api/accept', method: 'POST', body: {} });

    // M16 — replay now goes through apiClient (baseURL + auth), not a bare fetch.
    axiosMock.onPost('/api/accept').reply(200, { ok: true });

    renderHook(() => useOfflineSync());

    await act(async () => {
      listeners.forEach((cb) => cb({ isConnected: true }));
      // Allow the flush (incl. the dynamic import of apiClient) to settle.
      await new Promise((resolve) => setTimeout(resolve, 20));
    });

    expect(axiosMock.history['post']).toHaveLength(1);
    expect(axiosMock.history['post'][0]!.url).toBe('/api/accept');
    expect(await offlineQueue.getAll()).toHaveLength(0);
  });

  it('does not flush when NetInfo fires connected=false', async () => {
    await offlineQueue.enqueue({ url: '/api/accept', method: 'POST', body: {} });

    const mockFetchFn = jest.fn().mockResolvedValue({ ok: true });
    global.fetch = mockFetchFn as unknown as typeof fetch;

    renderHook(() => useOfflineSync());

    await act(async () => {
      listeners.forEach((cb) => cb({ isConnected: false }));
      await Promise.resolve();
    });

    expect(mockFetchFn).not.toHaveBeenCalled();
    expect(await offlineQueue.getAll()).toHaveLength(1);
  });

  it('removes NetInfo listener on unmount', () => {
    const { unmount } = renderHook(() => useOfflineSync());
    const initialCount = listeners.length;
    unmount();
    expect(listeners.length).toBeLessThan(initialCount);
  });
});
