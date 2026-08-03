/**
 * Failure-path tests for the provider EarningsScreen.
 *
 * Regression: the screen only destructured `isLoading`, never the error. A 403 on
 * /provider/wallet/* (accounts with no provider_profiles row) therefore rendered
 * "0.00 EUR" plus the "Aucune transaction" empty state — a silent lie rather than a failure.
 *
 * Covers:
 *  - both wallet queries 403 -> explicit provider-profile message, no fake empty state
 *  - both wallet queries 500 -> generic message
 *  - only transactions fails -> balance still rendered, inline error for the list
 *  - retry re-issues the requests
 *  - happy path still renders values
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

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

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    KPICard: ({ title, value }: any) => (
      <View testID={`kpi-${title}`}>
        <Text>{title}</Text>
        <Text>{String(value)}</Text>
      </View>
    ),
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    Skeleton: () => <View testID="skeleton" />,
    Divider: () => <View />,
    ErrorState: ({ message, onRetry, compact }: any) => (
      <View testID={compact ? 'error-state-compact' : 'error-state'}>
        <Text>{message}</Text>
        {onRetry && (
          <TouchableOpacity onPress={onRetry} accessibilityLabel="Réessayer">
            <Text>Réessayer</Text>
          </TouchableOpacity>
        )}
      </View>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 500: '#3b82f6', 600: '#2563eb' },
    surface: { 400: '#94a3b8', 500: '#64748b', 800: '#1e293b', 900: '#0f172a' },
    success: { 600: '#16a34a' },
    danger: { 600: '#dc2626' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
  },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20 },
    fontWeight: { semibold: '600', bold: '700' },
  },
  radius: { md: 12 },
  shadows: { xs: {} },
  // Le thème réel plutôt qu'un objet partiel écrit à la main : il n'a aucun effet de bord,
  // et un stub partiel périme à chaque jeton ajouté — c'est ce qui a cassé ces tests quand
  // les teintes sont apparues.
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

// ── Imports ───────────────────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { EarningsScreen } from '@/screens/EarningsScreen';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  };
}

const FORBIDDEN = {
  ok: false,
  error_code: 'http_error',
  message: 'Vous devez être prestataire pour utiliser ces endpoints.',
};

beforeEach(() => {
  apiMock.reset();
  apiMock.onGet('/provider/stripe-connect/status').reply(200, { onboarded: true });
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('EarningsScreen failure paths', () => {
  it('explains a 403 instead of rendering an empty wallet', async () => {
    apiMock.onGet('/provider/wallet/balance').reply(403, FORBIDDEN);
    apiMock.onGet('/provider/wallet/transactions').reply(403, FORBIDDEN);

    render(<EarningsScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText(/aucun portefeuille n'y est rattaché/)).toBeTruthy();
    });
    // The old behaviour: "0.00 EUR" + the empty-list state, as if the wallet were simply empty.
    expect(screen.queryByText('Aucune transaction')).toBeNull();
    expect(screen.queryByTestId('kpi-Disponible')).toBeNull();
  });

  it('falls back to a generic message on a server error', async () => {
    apiMock.onGet('/provider/wallet/balance').reply(500);
    apiMock.onGet('/provider/wallet/transactions').reply(500);

    render(<EarningsScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Impossible de charger vos revenus.')).toBeTruthy();
    });
  });

  it('keeps the balance visible when only transactions fail', async () => {
    apiMock
      .onGet('/provider/wallet/balance')
      .reply(200, { available: 120.5, pending: 30, currency: 'EUR' });
    apiMock.onGet('/provider/wallet/transactions').reply(500);

    render(<EarningsScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByTestId('kpi-Disponible')).toBeTruthy();
      expect(screen.getByText('Impossible de charger vos transactions.')).toBeTruthy();
    });
    expect(screen.getByTestId('error-state-compact')).toBeTruthy();
    expect(screen.queryByText('Aucune transaction')).toBeNull();
  });

  it('retries both wallet requests when the user taps Réessayer', async () => {
    apiMock.onGet('/provider/wallet/balance').reply(403, FORBIDDEN);
    apiMock.onGet('/provider/wallet/transactions').reply(403, FORBIDDEN);

    render(<EarningsScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByLabelText('Réessayer'));
    const before = apiMock.history['get']!.filter(c => c.url?.startsWith('/provider/wallet')).length;

    fireEvent.press(screen.getByLabelText('Réessayer'));

    await waitFor(() => {
      const after = apiMock.history['get']!.filter(c => c.url?.startsWith('/provider/wallet')).length;
      expect(after).toBeGreaterThan(before);
    });
  });

  it('still renders balance and transactions on the happy path', async () => {
    apiMock
      .onGet('/provider/wallet/balance')
      .reply(200, { available: 120.5, pending: 30, currency: 'EUR' });
    apiMock.onGet('/provider/wallet/transactions').reply(200, {
      data: [
        {
          id: 1,
          type: 'payout',
          amount: 90,
          currency: 'EUR',
          description: 'Mission Gand',
          created_at: '2026-07-01T10:00:00Z',
        },
      ],
    });

    render(<EarningsScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('120.50 EUR')).toBeTruthy();
      expect(screen.getByText('Mission Gand')).toBeTruthy();
    });
    expect(screen.queryByTestId('error-state')).toBeNull();
  });
});
