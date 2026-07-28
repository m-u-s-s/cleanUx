/**
 * Interaction tests for the provider DashboardScreen (Task 10 — carte-modal).
 *
 * Le dashboard est désormais map-first : toute la logique métier (présence, KPIs,
 * missions en attente, accès rapides, « Voir toutes les missions ») a migré dans
 * DashboardActionsSheet, piloté depuis cette page par un unique bouton « Actions ».
 *
 * Couvre :
 *  - Rendu de la salutation avec le prénom du prestataire
 *  - Rendu de la carte sur la page
 *  - Disparition de la grille d'accès rapides de la page
 *  - Ouverture du sheet au tap sur « Actions »
 *
 * Tout ce qui a quitté cette page reste couvert ailleurs :
 *  - Boutons de présence (endpoints v2 par transition) → __tests__/presence/hooks.test.ts
 *    (usePresence, le hook sous-jacent à PresenceToggle comme à PresencePill)
 *  - Accès rapides + KPIs + « Voir toutes les missions » → DashboardActionsSheet.test.tsx
 *  - Cartes d'aperçu des missions en attente + tap → navigate('MissionDetail', ...) →
 *    ProviderMap.test.tsx (le marqueur + callout de la carte remplacent les cartes)
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// PresencePill lit désormais son statut dans le cache React Query (source unique partagée avec
// PresenceToggle). React Query planifie ses notifications via un `setTimeout(0)` par défaut :
// cette macrotâche se déclenche après le `waitFor` qui l'attend, donc hors de toute portée
// `act()`, et React logue « not wrapped in act ». Même remède que dans ProviderMap.test.tsx :
// notification synchrone, dans ce fichier de test seulement.
notifyManager.setScheduler((callback) => callback());

// ── Module mocks ──────────────────────────────────────────────────────────────

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 5, name: 'Marie Curie', email: 'marie@test.com', role: 'provider' },
    isAuthenticated: true,
    isLoading: false,
    logout: jest.fn(),
  }),
}));

// `@/ui` et `@/theme` restent réels ici (comme dans ProviderMap.test.tsx et
// DashboardActionsSheet.test.tsx) : ce sont des modules autonomes sans dépendance native
// non mockée, et PresencePill (affichage seul, non stubbé — cf. ci-dessous) en a besoin
// pour son propre rendu (PulseDot notamment).

const mockExpand = jest.fn();
jest.mock('@/screens/components/ProviderMap', () => {
  const { View } = require('react-native');
  return { ProviderMap: () => <View testID="provider-map-slot" /> };
});
jest.mock('@/screens/components/DashboardActionsSheet', () => {
  const React = require('react');
  const { View } = require('react-native');
  return {
    DashboardActionsSheet: React.forwardRef((_p: any, ref: any) => {
      if (ref) ref.current = { expand: mockExpand, close: jest.fn() };
      return <View testID="actions-sheet" />;
    }),
  };
});

// ── Imports ───────────────────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { DashboardScreen } from '@/screens/DashboardScreen';

// ── Helpers ───────────────────────────────────────────────────────────────────

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  };
}

beforeEach(() => {
  apiMock.reset();
  jest.clearAllMocks();
  // PresencePill (composant d'affichage seul, non mocké dans ce fichier) hydrate son statut
  // via GET /provider/presence-v2 au montage : sans cette réponse, la requête partirait vers
  // un vrai réseau (non déterministe en test) au lieu de se résoudre proprement.
  apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('DashboardScreen interactions', () => {
  it('renders greeting with provider first name', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });

    // await waitFor pour laisser la requête de statut de présence (déclenchée par PresencePill
    // au montage) se résoudre avant la fin du test : sinon la mise à jour d'état se déclenche
    // hors d'un act().
    await waitFor(() => {
      expect(screen.getByText(/Bonjour, Marie/)).toBeTruthy();
    });
  });

  it('rend la carte sur la page', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map-slot')).toBeTruthy());
  });

  it('ne rend plus la grille d accès rapides sur la page', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => screen.getByTestId('provider-map-slot'));
    expect(screen.queryByText('Disponibilités')).toBeNull();
    expect(screen.queryByText('Badges')).toBeNull();
  });

  it('ouvre le sheet au tap sur Actions', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => screen.getByLabelText('Actions'));
    fireEvent.press(screen.getByLabelText('Actions'));
    expect(mockExpand).toHaveBeenCalled();
  });
});
