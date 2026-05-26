/**
 * Tests for the useRegister mutation hook.
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
import { useRegister } from '../useRegister';

const mock = new MockAdapter(apiClient);
const setItemAsync = ExpoSecureStore.setItemAsync as jest.Mock;

function wrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return React.createElement(QueryClientProvider, { client }, children);
}

const MOCK_USER = {
  id: 2,
  name: 'Bob',
  email: 'bob@example.com',
  phone: null,
  role: 'client',
  locale: 'fr',
  email_verified_at: null,
  created_at: '2025-01-01T00:00:00Z',
};

const VALID_INPUT = {
  name: 'Bob',
  email: 'bob@example.com',
  password: 'Password1!',
  passwordConfirmation: 'Password1!',
  acceptTerms: true,
};

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

describe('useRegister', () => {
  it('returns token and user on successful registration', async () => {
    mock.onPost('/auth/register').replyOnce(200, { token: 'reg-token', user: MOCK_USER });

    const { result } = renderHook(() => useRegister(), { wrapper });

    act(() => { result.current.mutate(VALID_INPUT); });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.token).toBe('reg-token');
    expect(result.current.data?.user.name).toBe('Bob');
  });

  it('stores the token in secure storage after registration', async () => {
    mock.onPost('/auth/register').replyOnce(200, { token: 'reg-token', user: MOCK_USER });

    const { result } = renderHook(() => useRegister(), { wrapper });

    act(() => { result.current.mutate(VALID_INPUT); });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(setItemAsync).toHaveBeenCalledWith('auth_token', 'reg-token');
  });

  it('surfaces validation errors on 422 response', async () => {
    mock.onPost('/auth/register').replyOnce(422, {
      ok: false,
      error_code: 'validation_error',
      message: 'Validation failed',
      errors: {
        email: ['The email has already been taken.'],
        password: ['The password must be at least 8 characters.'],
      },
    });

    const { result } = renderHook(() => useRegister(), { wrapper });

    act(() => { result.current.mutate(VALID_INPUT); });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.errors?.['email']).toContain('The email has already been taken.');
    expect(result.current.error?.errors?.['password']).toBeDefined();
  });

  it('sends accept_terms in the request body', async () => {
    mock.onPost('/auth/register').replyOnce(200, { token: 't', user: MOCK_USER });

    const { result } = renderHook(() => useRegister(), { wrapper });

    act(() => { result.current.mutate(VALID_INPUT); });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['accept_terms']).toBe(true);
    expect(body['device_name']).toBe('cleanux-mobile');
  });
});
