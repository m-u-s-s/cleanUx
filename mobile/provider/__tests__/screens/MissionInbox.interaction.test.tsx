/**
 * Interaction tests for the provider MissionInboxScreen.
 *
 * Covers:
 *  - Tap "Accepter" -> Alert shown -> confirm -> calls POST /provider/assignments/{id}/accept
 *  - Tap "Décliner" -> Alert shown -> confirm -> calls POST /provider/assignments/{id}/decline
 *  - Empty list renders empty state
 *  - Loading state renders Skeleton
 */
import React from 'react';
import { Alert } from 'react-native';
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

// L'écran gate sa surveillance GPS sur useIsFocused() (il est aussi l'onglet « Missions », donc
// monté en permanence). Ce hook exige un NavigationContainer que ce fichier ne monte pas.
const mockIsFocused = { current: true };
jest.mock('@react-navigation/native', () => ({
  useIsFocused: () => mockIsFocused.current,
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
    // Crochet d'accessibilite, pas un composant : l'ecran ne s'anime pas sous test.
    useEntree: () => undefined,
    useDuree: () => 0,
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress, variant, size }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} testID={`btn-${label}`}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View testID="skeleton" />,
    EmptyState: ({ title }: any) => <Text testID="empty-state">{title}</Text>,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    a11y: { announce: jest.fn() },
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 500: '#3b82f6', 600: '#2563eb' }, surface: { 500: '#64748b', 600: '#475569', 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, xl: 20 }, fontWeight: { semibold: '600', bold: '700' } },
  radius: { md: 12 },
  shadows: { soft: {}, xs: {} },
  // Le thème réel plutôt qu'un objet partiel écrit à la main : il n'a aucun effet de bord,
  // et un stub partiel périme à chaque jeton ajouté — c'est ce qui a cassé ces tests quand
  // les teintes sont apparues.
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

// react-native-reanimated Animated.View stub
jest.mock('react-native-reanimated', () => {
  const { View } = require('react-native');
  return {
    __esModule: true,
    default: { View, createAnimatedComponent: (c: any) => c },
    FadeIn: { duration: () => ({ duration: 280 }) },
  };
});

// ── Imports ───────────────────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { MissionInboxScreen } from '@/screens/MissionInboxScreen';

// ── Helpers ───────────────────────────────────────────────────────────────────

const apiMock = new MockAdapter(apiClient);

const MOCK_ASSIGNMENT = {
  id: 1,
  mission_id: 10,
  assignment_status: 'assigned',
  expires_at: null,
  remaining_seconds: null,
  booking_id: 100,
  service_name: 'Nettoyage',
  client_name: 'Jean Martin',
  address: '5 Rue du Bois',
  city: 'Liege',
  postal_code: '4000',
  scheduled_date: '2026-06-10',
  scheduled_time: '09:00',
  estimated_duration_minutes: 90,
  latitude: 50.6326,
  longitude: 5.5797,
  created_at: '2026-06-09T09:00:00Z',
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
  jest.clearAllMocks();
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('MissionInboxScreen interactions', () => {
  it('renders mission service name after data loads', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Nettoyage')).toBeTruthy();
      expect(screen.getByText('Jean Martin')).toBeTruthy();
    });
  });

  it('tap "Accepter" triggers Alert and confirm calls POST accept', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onPost('/provider/assignments/1/accept').reply(200, { ok: true });

    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(
      (_title, _msg, buttons) => {
        // Simulate pressing the "Accepter" confirm button (index 1)
        const confirmBtn = buttons?.find((b: any) => b.text === 'Accepter');
        confirmBtn?.onPress?.();
      },
    );

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Nettoyage'));

    act(() => {
      fireEvent.press(screen.getByTestId('btn-Accepter'));
    });

    expect(alertSpy).toHaveBeenCalledWith(
      'Accepter',
      expect.stringContaining('Nettoyage'),
      expect.any(Array),
    );

    await waitFor(() => {
      expect(apiMock.history['post']).toHaveLength(1);
      expect(apiMock.history['post']![0]!.url).toBe('/provider/assignments/1/accept');
    });
  });

  it('tap "Décliner" triggers Alert and confirm calls POST decline', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });
    apiMock.onPost('/provider/assignments/1/decline').reply(200, { ok: true });

    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(
      (_title, _msg, buttons) => {
        const confirmBtn = buttons?.find((b: any) => b.text === 'Décliner');
        confirmBtn?.onPress?.();
      },
    );

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Nettoyage'));

    act(() => {
      fireEvent.press(screen.getByTestId('btn-Décliner'));
    });

    expect(alertSpy).toHaveBeenCalledWith(
      'Décliner',
      'Décliner cette mission ?',
      expect.any(Array),
    );

    await waitFor(() => {
      expect(apiMock.history['post']).toHaveLength(1);
      expect(apiMock.history['post']![0]!.url).toBe('/provider/assignments/1/decline');
    });
  });

  it('shows empty state when inbox is empty', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeTruthy();
      expect(screen.getByText('Aucune mission en attente')).toBeTruthy();
    });
  });

  it('shows skeleton while loading', async () => {
    // Never resolves
    apiMock.onGet('/provider/assignments/inbox').reply(() => new Promise(() => undefined));

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    // await waitFor pour laisser la chaîne de permission GPS (useCurrentPosition) se résoudre
    // avant l'assertion finale : sinon setPermission('granted') se déclenche après la fin du
    // test, hors d'un act(), et déclenche l'avertissement React.
    await waitFor(() => {
      expect(screen.getByTestId('skeleton')).toBeTruthy();
    });
  });

  it('tap "Annuler" in Accept dialog does not call the API', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });

    jest.spyOn(Alert, 'alert').mockImplementation(
      (_title, _msg, buttons) => {
        // Simulate pressing "Annuler" (index 0)
        const cancelBtn = buttons?.find((b: any) => b.text === 'Annuler');
        cancelBtn?.onPress?.();
      },
    );

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Nettoyage'));

    act(() => {
      fireEvent.press(screen.getByTestId('btn-Accepter'));
    });

    expect(apiMock.history['post'] ?? []).toHaveLength(0);
  });
});
