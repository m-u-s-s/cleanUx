/**
 * Interaction tests for the client HomeScreen.
 *
 * Covers:
 *  - Tap "Mes réservations" (feuille d'actions) -> navigate('MainTabs', { screen: 'Bookings' })
 *  - Tap "Messagerie" (feuille d'actions) -> navigate('ChatList')
 *  - Tap "Fidélité" (feuille d'actions) -> navigate('Loyalty')
 *  - Tap "Réserver un service" CTA -> navigate('BookingWizard')
 *  - Tap "Réserver un service" (action flottante) -> navigate('BookingWizard')
 *  - Tap active booking card -> navigate('BookingDetail', { bookingId })
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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
    user: { id: 1, name: 'Alice Dupont', email: 'alice@test.com', role: 'client' },
    logout: jest.fn(),
    isAuthenticated: true,
    isLoading: false,
  }),
}));

jest.mock('@/booking', () => ({
  useBookings: () => ({
    data: [
      {
        id: 10,
        status: 'confirmed',
        service_name: 'Nettoyage',
        scheduled_date: '2026-06-01',
        scheduled_time: '10:00',
        address: '1 rue Test',
        city: 'Bruxelles',
        postal_code: '1000',
        total_price: 60,
        created_at: '',
      },
    ],
    isLoading: false,
    isError: false,
  }),
}));

jest.mock('@/theme', () => ({
  colors: { brand: { 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb' }, surface: { 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24 }, fontWeight: { medium: '500', semibold: '600', bold: '700' } },
  radius: { md: 12 },
  shadows: { soft: {}, xs: {} },
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#f8fafc', textMuted: '#64748b', textSecondary: '#94a3b8' }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    KPICard: ({ title, value }: any) => (
      <View>
        <Text>{title}</Text>
        <Text>{String(value)}</Text>
      </View>
    ),
    Avatar: () => <View />,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    Icon: () => <View />,
    // La feuille d'actions emploie ces deux-la : sans eux dans le faux, l'ecran leve avant meme
    // d'etre rendu.
    Divider: () => <View />,
    BottomSheet: ({ children }: any) => <View>{children}</View>,
  };
});

// ── Import after mocks ────────────────────────────────────────────────────────

import { HomeScreen } from '../../src/screens/HomeScreen';

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(() => {
  mockNavigate.mockClear();
  jest.clearAllMocks();
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('HomeScreen interactions', () => {
  it('renders greeting with user first name', () => {
    render(<HomeScreen />);
    expect(screen.getByText(/Bonjour, Alice/)).toBeTruthy();
  });

  it('tap "Mes réservations" navigates to MainTabs', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByLabelText('Mes réservations'));
    // Le paramètre imbriqué est indispensable : sans lui, la navigation atterrit sur l'onglet par
    // défaut de MainTabs au lieu des réservations.
    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Bookings' });
  });

  it('tap "Messagerie" navigates to ChatList', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByLabelText('Messagerie'));
    expect(mockNavigate).toHaveBeenCalledWith('ChatList', undefined);
  });

  it('tap "Fidélité" navigates to Loyalty', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByLabelText('Fidélité'));
    expect(mockNavigate).toHaveBeenCalledWith('Loyalty', undefined);
  });

  it('tap "Réserver un service" CTA navigates to BookingWizard', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByText('Réserver un service'));
    expect(mockNavigate).toHaveBeenCalledWith('BookingWizard');
  });

  it('shows active booking card with service name', () => {
    render(<HomeScreen />);
    expect(screen.getByText('Nettoyage')).toBeTruthy();
  });

  it('tap active booking card navigates to BookingDetail with bookingId', () => {
    render(<HomeScreen />);
    fireEvent.press(screen.getByText('Nettoyage'));
    expect(mockNavigate).toHaveBeenCalledWith('BookingDetail', { bookingId: 10 });
  });
});

describe('HomeScreen — first-time user', () => {
  it('shows welcome card with BookingWizard CTA when no bookings', async () => {
    // Override the useBookings mock to return empty data for this test.
    // We use the already-imported HomeScreen (no resetModules) to avoid the
    // dual-React-instance issue that causes "Cannot read properties of null
    // (reading 'useCallback')" in React 19 + jest-expo.
    jest.spyOn(
      jest.requireMock('@/booking') as { useBookings: () => unknown },
      'useBookings',
    ).mockReturnValue({ data: [], isLoading: false, isError: false });

    render(<HomeScreen />);

    await waitFor(() => {
      expect(screen.getByText('Réserver un service')).toBeTruthy();
    });

    fireEvent.press(screen.getByText('Réserver un service'));
    expect(mockNavigate).toHaveBeenCalledWith('BookingWizard');
  });
});
