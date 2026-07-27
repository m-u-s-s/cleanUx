/**
 * Interaction tests for the provider DashboardScreen.
 *
 * Covers:
 *  - Renders greeting with provider first name
 *  - Presence buttons call setPresenceStatus -> one POST per v2 transition endpoint
 *  - Tap "Disponibilités" quick action -> navigate('Availability')
 *  - Tap "Badges" quick action -> navigate('Badges')
 *  - Tap "Messagerie" quick action -> navigate('ProviderChatList')
 *  - Tap "Voir toutes les missions" -> navigate to MainTabs Missions
 *  - Tap a mission card -> navigate to MissionDetail
 */
import React from 'react';
import { fireEvent, render, screen, waitFor, act } from '@testing-library/react-native';
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

const mockNavigate = jest.fn();

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 5, name: 'Marie Curie', email: 'marie@test.com', role: 'provider' },
    isAuthenticated: true,
    isLoading: false,
    logout: jest.fn(),
  }),
}));

jest.mock('@/realtime', () => ({
  useChannel: jest.fn(),
  RealtimeProvider: ({ children }: any) => children,
  useRealtime: () => ({ client: null }),
  useSocketConfig: () => ({ host: '', key: '' }),
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
    Avatar: ({ name }: any) => <Text>{name}</Text>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} testID={`btn-${label}`}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    Skeleton: () => <View testID="skeleton" />,
    PulseDot: () => <View />,
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 500: '#3b82f6', 600: '#2563eb' },
    surface: { 100: '#f1f5f9', 500: '#64748b', 600: '#475569', 800: '#1e293b', 900: '#0f172a' },
    danger: { 500: '#ef4444', 600: '#dc2626' },
  },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, lg: 18, '2xl': 24 }, fontWeight: { medium: '500', semibold: '600', bold: '700' } },
  radius: { sm: 6, md: 12 },
  shadows: { xs: {} },
}));

// ── Imports ───────────────────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { DashboardScreen } from '@/screens/DashboardScreen';

// ── Helpers ───────────────────────────────────────────────────────────────────

const apiMock = new MockAdapter(apiClient);

const MOCK_ASSIGNMENT = {
  id: 2,
  mission_id: 20,
  assignment_status: 'assigned',
  expires_at: null,
  remaining_seconds: null,
  booking_id: 200,
  service_name: 'Peinture',
  client_name: 'Paul Klee',
  address: '10 Rue des Arts',
  city: 'Gent',
  postal_code: '9000',
  scheduled_date: '2026-06-15',
  scheduled_time: '14:00',
  estimated_duration_minutes: 90,
  latitude: 51.0543,
  longitude: 3.7174,
  created_at: '2026-06-14T09:00:00Z',
};

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
  mockNavigate.mockClear();
  jest.clearAllMocks();
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('DashboardScreen interactions', () => {
  it('renders greeting with provider first name', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, currency: 'EUR', pending: 0 });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) et les deux
    // requêtes réseau du montage se résoudre avant l'assertion finale : sinon setPermission
    // ('granted') ou la notification React Query se déclenchent après la fin du test, hors
    // d'un act(). On inclut la condition réseau car le texte de salutation, lui, est déjà vrai
    // dès le premier rendu synchrone (issu du mock useAuth) et ne forcerait donc aucun sondage réel.
    await waitFor(() => {
      expect(screen.getByText(/Bonjour, Marie/)).toBeTruthy();
      const urls = (apiMock.history['get'] ?? []).map(c => c.url);
      expect(urls).toEqual(expect.arrayContaining(['/provider/assignments/inbox', '/provider/wallet/balance']));
    });
  });

  it('tap "En ligne" presence button posts to the v2 online endpoint', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });
    apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
    // Presence v2 has one endpoint per transition — the legacy Phase 11 route
    // /provider/presence/online required a provider_profiles row (403) and lat+lng (422).
    apiMock.onPost('/provider/presence-v2/online').reply(200, { data: { status: 'online' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    act(() => {
      fireEvent.press(screen.getByText('En ligne'));
    });

    await waitFor(() => {
      const urls = (apiMock.history['post'] ?? []).map(c => c.url);
      expect(urls).toContain('/provider/presence-v2/online');
      expect(urls).not.toContain('/provider/presence/online');
    });
  });

  it('tap "Occupé" presence button posts to the v2 busy endpoint', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });
    apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
    apiMock.onPost('/provider/presence-v2/busy').reply(200, { data: { status: 'busy' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    act(() => {
      fireEvent.press(screen.getByText('Occupé'));
    });

    await waitFor(() => {
      const urls = (apiMock.history['post'] ?? []).map(c => c.url);
      expect(urls).toContain('/provider/presence-v2/busy');
    });
  });

  it('tap "Hors ligne" presence button posts to the v2 offline endpoint', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });
    apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
    apiMock.onPost('/provider/presence-v2/offline').reply(200, { data: { status: 'offline' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    // Initial status is 'offline', so the header label and the button both read
    // "Hors ligne". The button is the last matching node (rendered after header).
    const offlineNodes = screen.getAllByText('Hors ligne');
    act(() => {
      fireEvent.press(offlineNodes[offlineNodes.length - 1]!);
    });

    await waitFor(() => {
      const urls = (apiMock.history['post'] ?? []).map(c => c.url);
      expect(urls).toContain('/provider/presence-v2/offline');
    });
  });

  it('tap "Disponibilités" quick action navigates to Availability', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Disponibilités'));

    fireEvent.press(screen.getByText('Disponibilités'));
    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) se résoudre
    // avant l'assertion finale, sinon setPermission('granted') se déclenche hors d'un act().
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('Availability');
    });
  });

  it('tap "Revenus" quick action navigates to the Earnings tab', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Revenus'));

    fireEvent.press(screen.getByText('Revenus'));
    // Earnings is a tab *inside* MainTabs. navigate('MainTabs') with no params is a no-op
    // when the dashboard is already the focused tab — the button did nothing at all.
    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) se résoudre
    // avant l'assertion finale, sinon setPermission('granted') se déclenche hors d'un act().
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Earnings' });
    });
  });

  it('tap "Badges" quick action navigates to Badges', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Badges'));

    fireEvent.press(screen.getByText('Badges'));
    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) se résoudre
    // avant l'assertion finale, sinon setPermission('granted') se déclenche hors d'un act().
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('Badges');
    });
  });

  it('tap "Messagerie" quick action navigates to ProviderChatList', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 0, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Messagerie'));

    fireEvent.press(screen.getByText('Messagerie'));
    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) se résoudre
    // avant l'assertion finale, sinon setPermission('granted') se déclenche hors d'un act().
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('ProviderChatList');
    });
  });

  it('shows pending mission card when assignments exist', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 80, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Peinture')).toBeTruthy();
      expect(screen.getByText(/Paul Klee/)).toBeTruthy();
    });
  });

  it('tap pending mission card navigates to MissionDetail', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 80, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Peinture'));

    fireEvent.press(screen.getByText('Peinture'));
    expect(mockNavigate).toHaveBeenCalledWith('MissionDetail', { missionId: 200 });
  });

  it('tap "Voir toutes les missions" navigates to MainTabs Missions', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { data: { available: 80, currency: 'EUR' } });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Voir toutes les missions'));

    fireEvent.press(screen.getByLabelText('Voir toutes les missions'));
    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Missions' });
  });

  it('shows KPI cards with correct values', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, currency: 'EUR', pending: 0 });

    render(<DashboardScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByTestId('kpi-Missions en attente')).toBeTruthy();
      expect(screen.getByTestId('kpi-Solde disponible')).toBeTruthy();
    });
  });
});
