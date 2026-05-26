/**
 * Tests for the AuthProvider component and context.
 */
import React from 'react';
import { Text } from 'react-native';
import { render, screen, act, waitFor } from '@testing-library/react-native';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

import * as ExpoSecureStore from 'expo-secure-store';
import { apiClient } from '@/api';
import { AuthProvider, AuthContext } from '../AuthProvider';

const mock = new MockAdapter(apiClient);
const getItemAsync = ExpoSecureStore.getItemAsync as jest.Mock;
const deleteItemAsync = ExpoSecureStore.deleteItemAsync as jest.Mock;

const MOCK_USER = {
  id: 1, name: 'Alice', email: 'alice@example.com', phone: null,
  role: 'client', locale: 'fr', email_verified_at: null, created_at: '2025-01-01',
};

function TestConsumer() {
  return (
    <AuthContext.Consumer>
      {({ user, isAuthenticated, isLoading }) => (
        <Text testID="output">
          {isLoading ? 'loading' : isAuthenticated ? `auth:${user!.email}` : 'anon'}
        </Text>
      )}
    </AuthContext.Consumer>
  );
}

function LogoutConsumer() {
  return (
    <AuthContext.Consumer>
      {({ logout }) => (
        <Text testID="logout" onPress={logout}>logout</Text>
      )}
    </AuthContext.Consumer>
  );
}

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
  getItemAsync.mockResolvedValue(null);
});

describe('AuthProvider', () => {
  it('shows anonymous state when no token is stored', async () => {
    getItemAsync.mockResolvedValue(null);

    render(
      <AuthProvider><TestConsumer /></AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('output').props.children).toBe('anon'));
  });

  it('fetches /auth/me and sets user when token exists', async () => {
    getItemAsync.mockResolvedValue('valid-token');
    mock.onGet('/auth/me').replyOnce(200, { user: MOCK_USER });

    render(
      <AuthProvider><TestConsumer /></AuthProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId('output').props.children).toBe('auth:alice@example.com'),
    );
  });

  it('clears token and shows anon when /auth/me fails', async () => {
    getItemAsync.mockResolvedValue('bad-token');
    mock.onGet('/auth/me').replyOnce(401, { ok: false, error_code: 'token_grace_expired', message: 'Expired' });

    render(
      <AuthProvider><TestConsumer /></AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('output').props.children).toBe('anon'));
    expect(deleteItemAsync).toHaveBeenCalled();
  });

  it('logout clears token and resets user to null', async () => {
    getItemAsync.mockResolvedValue('valid-token');
    mock.onGet('/auth/me').replyOnce(200, { user: MOCK_USER });
    mock.onPost('/auth/logout').replyOnce(200, {});

    render(
      <AuthProvider>
        <TestConsumer />
        <LogoutConsumer />
      </AuthProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId('output').props.children).toBe('auth:alice@example.com'),
    );

    await act(async () => {
      screen.getByTestId('logout').props.onPress();
    });

    await waitFor(() => expect(screen.getByTestId('output').props.children).toBe('anon'));
    expect(deleteItemAsync).toHaveBeenCalled();
  });

  it('isLoading is true initially then resolves', async () => {
    let resolveMe!: () => void;
    const delayedMe = new Promise<void>((r) => { resolveMe = r; });
    getItemAsync.mockResolvedValue('token');
    mock.onGet('/auth/me').reply(() =>
      delayedMe.then(() => [200, { user: MOCK_USER }]),
    );

    render(<AuthProvider><TestConsumer /></AuthProvider>);

    expect(screen.getByTestId('output').props.children).toBe('loading');

    await act(async () => { resolveMe(); });

    await waitFor(() =>
      expect(screen.getByTestId('output').props.children).toBe('auth:alice@example.com'),
    );
  });
});
