import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// React Query planifie ses notifications via un `setTimeout(0)` par défaut : cette macrotâche
// peut se déclencher après qu'un premier `waitFor` a déjà résolu (dès le premier rendu), donc
// hors de toute portée `act()`, et React logue « not wrapped in act ». On force ici une
// notification synchrone, dans ce fichier de test seulement, pour que la mise à jour de
// l'inbox de missions reste attribuable au rendu ou au `waitFor` qui l'attend.
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

jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: jest.fn() }) }));

const mockPermission = { current: 'granted' as 'pending' | 'granted' | 'denied' };
jest.mock('@/tracking', () => ({
  useGpsWatcher: () => ({ permission: mockPermission.current }),
  distanceKmTo: jest.requireActual('../../src/tracking/distance').distanceKmTo,
  formatDistance: jest.requireActual('../../src/tracking/distance').formatDistance,
}));

// `react-native-maps` est déjà redirigé vers __mocks__/react-native-maps par moduleNameMapper :
// il suffit donc de faire renvoyer ce module (ou null) par loadMapModule.
const mockMapModule = { current: true };
jest.mock('@/maps', () => ({
  loadMapModule: () => {
    if (!mockMapModule.current) return null;
    const maps = require('react-native-maps');
    return { MapView: maps.default, Marker: maps.Marker, Callout: maps.Callout };
  },
}));

import { apiClient } from '@/api';
import { ProviderMap } from '@/screens/components/ProviderMap';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockMapModule.current = true;
  mockPermission.current = 'granted';
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
});

describe('ProviderMap', () => {
  it('rend la carte quand le module natif est disponible', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
  });

  it('rend le placeholder texte quand le module natif est absent', async () => {
    mockMapModule.current = false;

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-fallback')).toBeTruthy());
    expect(screen.queryByTestId('provider-map')).toBeNull();
  });

  it('explique une permission GPS refusée', async () => {
    mockPermission.current = 'denied';

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-permission-notice')).toBeTruthy());
  });

  it('annonce l absence de mission en attente', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText(/Aucune mission en attente/)).toBeTruthy());
  });

  it('affiche une erreur récupérable quand l inbox échoue', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(500);

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText(/Réessayer/)).toBeTruthy());
    expect(screen.getByTestId('provider-map')).toBeTruthy();
  });
});
