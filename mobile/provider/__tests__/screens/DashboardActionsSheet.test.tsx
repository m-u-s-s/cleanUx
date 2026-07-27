import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// React Query planifie ses notifications via un `setTimeout(0)` par défaut : cette macrotâche
// peut se déclencher après qu'un premier `waitFor` a déjà résolu, donc hors de toute portée
// `act()`, et React logue « not wrapped in act ». On force ici une notification synchrone,
// dans ce fichier de test seulement (voir __tests__/screens/ProviderMap.test.tsx pour le même
// pattern).
notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: mockNavigate }) }));

// Le sheet gorhom est remplacé par un conteneur simple : on teste le contenu et le câblage,
// pas l'animation native.
jest.mock('@/ui', () => {
  const actual = jest.requireActual('@/ui');
  const { View } = require('react-native');
  const React = require('react');
  return { ...actual, BottomSheet: React.forwardRef(({ children }: any, _ref: any) => <View>{children}</View>) };
});

import { apiClient } from '@/api';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockNavigate.mockClear();
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
  apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, pending: 0, currency: 'EUR' });
  apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
});

describe('DashboardActionsSheet', () => {
  it('contient les quatre accès rapides et les boutons de présence', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Disponibilités')).toBeTruthy());
    expect(screen.getByText('Badges')).toBeTruthy();
    expect(screen.getByText('Revenus')).toBeTruthy();
    expect(screen.getByText('Messagerie')).toBeTruthy();
    expect(screen.getByText('Occupé')).toBeTruthy();
  });

  it('navigue vers l onglet Revenus', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Revenus'));
    fireEvent.press(screen.getByText('Revenus'));

    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Earnings' });
  });

  it('affiche les KPIs', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText('Missions en attente')).toBeTruthy());
    expect(screen.getByText('Solde disponible')).toBeTruthy();
  });
});
