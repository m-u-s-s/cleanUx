/**
 * Tests for DisputesScreen — opening a dispute (was read-only: list only).
 */
import React from 'react';
import { Alert } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  },
}));

import { apiClient } from '@/api';
import { DisputesScreen } from '../DisputesScreen';

const mock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client }, children);
  };
}

beforeEach(() => {
  mock.reset();
  jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
});

test('opening a dispute posts to /client/disputes', async () => {
  mock.onGet('/client/disputes').reply(200, { data: [] });
  mock.onPost('/client/disputes').reply(201, { data: { id: 1, status: 'open' } });

  render(<DisputesScreen />, { wrapper: makeWrapper() });

  fireEvent.press(await screen.findByText('Ouvrir un litige'));

  fireEvent.changeText(screen.getByPlaceholderText('Sujet'), 'Prestation incomplète');
  fireEvent.changeText(screen.getByPlaceholderText('Décrivez le problème'), 'La prestation a été bâclée et incomplète.');
  fireEvent.press(screen.getByText('Qualité'));
  fireEvent.press(screen.getByText('Envoyer'));

  await waitFor(() => {
    const posted = mock.history.post.find(r => r.url === '/client/disputes');
    expect(posted).toBeTruthy();
    expect(JSON.parse(posted!.data)).toMatchObject({
      subject: 'Prestation incomplète',
      category: 'quality',
    });
  });
});
