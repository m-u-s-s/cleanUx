/**
 * Tests for the useLogin mutation hook.
 */
import { renderHook, act, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

import * as ExpoSecureStore from 'expo-secure-store';
import { apiClient } from '@/api';
import { useLogin } from '../useLogin';

const mock = new MockAdapter(apiClient);
const setItemAsync = ExpoSecureStore.setItemAsync as jest.Mock;

function wrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return React.createElement(QueryClientProvider, { client }, children);
}

const MOCK_USER = {
  id: 1,
  name: 'Alice',
  email: 'alice@example.com',
  phone: null,
  role: 'client',
  locale: 'fr',
  email_verified_at: null,
  created_at: '2025-01-01T00:00:00Z',
};

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

describe('useLogin', () => {
  it('returns user and token on successful login', async () => {
    mock.onPost('/auth/login').replyOnce(200, {
      token: 'valid-token',
      user: MOCK_USER,
    });

    const { result } = renderHook(() => useLogin(), { wrapper });

    act(() => {
      result.current.mutate({ email: 'alice@example.com', password: 'secret' });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.token).toBe('valid-token');
    expect(result.current.data?.user.email).toBe('alice@example.com');
  });

  it('stores the token in secure storage on success', async () => {
    mock.onPost('/auth/login').replyOnce(200, { token: 'stored-token', user: MOCK_USER });

    const { result } = renderHook(() => useLogin(), { wrapper });

    act(() => {
      result.current.mutate({ email: 'alice@example.com', password: 'secret' });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(setItemAsync).toHaveBeenCalledWith('auth_token', 'stored-token');
  });

  it('exposes an error when credentials are invalid', async () => {
    // The API returns 422 for bad credentials (not 401 — 401 triggers the
    // token-refresh interceptor which would mask the original error).
    mock.onPost('/auth/login').replyOnce(422, {
      ok: false,
      error_code: 'invalid_credentials',
      message: 'These credentials do not match our records.',
    });

    const { result } = renderHook(() => useLogin(), { wrapper });

    act(() => {
      result.current.mutate({ email: 'bad@example.com', password: 'wrong' });
    });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.errorCode).toBe('invalid_credentials');
  });

  it('is in loading state while the request is pending', async () => {
    let resolve: (value: unknown) => void;
    const pending = new Promise((r) => { resolve = r; });
    mock.onPost('/auth/login').reply(() => pending as Promise<[number, unknown]>);

    const { result } = renderHook(() => useLogin(), { wrapper });

    act(() => {
      result.current.mutate({ email: 'alice@example.com', password: 'secret' });
    });

    await waitFor(() => expect(result.current.isPending).toBe(true));

    // Clean up — resolve the pending promise
    act(() => { resolve!([200, { token: 't', user: MOCK_USER }]); });
  });

  it('sends device_name defaulting to brio-mobile', async () => {
    mock.onPost('/auth/login').replyOnce(200, { token: 't', user: MOCK_USER });

    const { result } = renderHook(() => useLogin(), { wrapper });

    act(() => {
      result.current.mutate({ email: 'alice@example.com', password: 'secret' });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['device_name']).toBe('brio-mobile');
  });
});
