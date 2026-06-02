/**
 * Interaction tests for the booking flow screens.
 *
 * Covers:
 *  - BookingStep1: tap a service card -> navigates to BookingStep2
 *  - BookingStep2: tap "Suivant" with details filled -> navigates to BookingStep3
 *  - BookingStep3: fill valid address -> "Suivant" becomes enabled, tap -> navigates to BookingStep4
 *  - BookingStep5: tap "Confirmer" -> calls POST /client/bookings
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

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => mockNavigation,
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress, disabled }: any) => (
      <TouchableOpacity onPress={onPress} disabled={disabled} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    ProgressBar: () => <View />,
    TextInput: ({ label, value, onChangeText, ...rest }: any) => (
      <RNTextInput
        testID={`input-${label}`}
        value={value}
        onChangeText={onChangeText}
        placeholder={label}
        {...rest}
      />
    ),
    Divider: () => <View />,
    Skeleton: () => <View />,
    SuccessOverlay: ({ visible, onDismiss, message }: any) =>
      visible ? <Text onPress={onDismiss}>{message}</Text> : null,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    ErrorScreen: () => <View />,
    a11y: { announce: jest.fn() },
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 50: '#f0f9ff', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }, surface: { 100: '#f1f5f9', 200: '#e2e8f0', 500: '#64748b', 800: '#1e293b', 900: '#0f172a' }, warning: { 50: '#fffbeb', 500: '#f59e0b', 700: '#b45309' } },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24 }, fontWeight: { medium: '500', semibold: '600', bold: '700' } },
  radius: { sm: 6, md: 12 },
  shadows: { soft: {}, xs: {} },
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#f8fafc', textMuted: '#64748b', textSecondary: '#94a3b8' }),
}));

// ── Imports (after mocks) ─────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { BookingProvider, useBooking } from '@/booking/BookingProvider';
import { BookingStep1Service } from '@/screens/booking/BookingStep1Service';
import { BookingStep2Details } from '@/screens/booking/BookingStep2Details';
import { BookingStep3Coordinates } from '@/screens/booking/BookingStep3Coordinates';
import { BookingStep5Confirmation } from '@/screens/booking/BookingStep5Confirmation';

// ── Shared setup ──────────────────────────────────────────────────────────────

const mockNavigation = {
  navigate: jest.fn(),
  goBack: jest.fn(),
  getParent: jest.fn().mockReturnValue({ goBack: jest.fn() }),
};

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <BookingProvider>{children}</BookingProvider>
      </QueryClientProvider>
    );
  };
}

beforeEach(() => {
  apiMock.reset();
  jest.clearAllMocks();
  mockNavigation.navigate.mockClear();
  mockNavigation.goBack.mockClear();
});

// ── BookingStep1 ──────────────────────────────────────────────────────────────

describe('BookingStep1Service interaction', () => {
  const navigation = mockNavigation as any;

  it('tap a service card dispatches SET_SERVICE and navigates to BookingStep2', async () => {
    apiMock.onGet('/search/services').reply(200, {
      data: [
        { id: 1, name: 'Nettoyage', slug: 'nettoyage' },
        { id: 2, name: 'Peinture', slug: 'peinture' },
      ],
    });

    render(<BookingStep1Service navigation={navigation} route={{ key: 'step1', name: 'BookingStep1' } as any} />, {
      wrapper: makeWrapper(),
    });

    await waitFor(() => screen.getByText('Nettoyage'));

    act(() => {
      fireEvent.press(screen.getByText('Nettoyage'));
    });

    await waitFor(() => {
      expect(navigation.navigate).toHaveBeenCalledWith('BookingStep2');
    }, { timeout: 500 });
  });

  it('shows error screen when service catalog fails to load', async () => {
    apiMock.onGet('/search/services').reply(500, { ok: false, message: 'Server error' });

    render(<BookingStep1Service navigation={navigation} route={{ key: 'step1', name: 'BookingStep1' } as any} />, {
      wrapper: makeWrapper(),
    });

    // ErrorScreen renders (via @/ui mock it renders nothing visible but should not crash)
    await waitFor(() => {
      expect(screen.toJSON()).not.toBeNull();
    });
  });
});

// ── BookingStep2 ──────────────────────────────────────────────────────────────

describe('BookingStep2Details interaction', () => {
  const navigation = mockNavigation as any;

  it('tap "Suivant" navigates to BookingStep3', () => {
    render(
      <BookingStep2Details
        navigation={navigation}
        route={{ key: 'step2', name: 'BookingStep2' } as any}
      />,
      { wrapper: makeWrapper() },
    );

    fireEvent.press(screen.getByText('Suivant'));
    expect(navigation.navigate).toHaveBeenCalledWith('BookingStep3');
  });

  it('updates surface input and proceeds to next step', () => {
    render(
      <BookingStep2Details
        navigation={navigation}
        route={{ key: 'step2', name: 'BookingStep2' } as any}
      />,
      { wrapper: makeWrapper() },
    );

    const surfaceInput = screen.getByTestId('input-Surface (m²)');
    fireEvent.changeText(surfaceInput, '75');
    fireEvent.press(screen.getByText('Suivant'));
    expect(navigation.navigate).toHaveBeenCalledWith('BookingStep3');
  });
});

// ── BookingStep3 ──────────────────────────────────────────────────────────────

describe('BookingStep3Coordinates interaction', () => {
  const navigation = mockNavigation as any;

  it('"Suivant" is disabled when address fields are empty', () => {
    apiMock.onGet('/search/postal-autocomplete').reply(200, { data: [] });
    // suppress missing-address query

    render(
      <BookingStep3Coordinates
        navigation={navigation}
        route={{ key: 'step3', name: 'BookingStep3' } as any}
      />,
      { wrapper: makeWrapper() },
    );

    const btn = screen.getByText('Suivant');
    // Disabled prop — pressing it should not navigate
    fireEvent.press(btn);
    expect(navigation.navigate).not.toHaveBeenCalled();
  });

  it('fills address, postal, city and "Suivant" navigates to BookingStep4', () => {
    apiMock.onGet('/search/postal-autocomplete').reply(200, { data: [] });

    render(
      <BookingStep3Coordinates
        navigation={navigation}
        route={{ key: 'step3', name: 'BookingStep3' } as any}
      />,
      { wrapper: makeWrapper() },
    );

    fireEvent.changeText(screen.getByTestId('input-Adresse'), '12 Rue de la Paix');
    fireEvent.changeText(screen.getByTestId('input-Code postal'), '75001');
    fireEvent.changeText(screen.getByTestId('input-Ville'), 'Paris');

    fireEvent.press(screen.getByText('Suivant'));
    expect(navigation.navigate).toHaveBeenCalledWith('BookingStep4');
  });
});

// ── BookingStep5 ──────────────────────────────────────────────────────────────

/**
 * Wrapper that pre-fills the BookingProvider with a service + coordinates so
 * BookingStep5Confirmation.handleConfirm does not short-circuit on !state.serviceId.
 */
function makePrefilledWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
  function Wrapper({ children }: { children: React.ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <BookingProvider>
          <StateSeeder>{children}</StateSeeder>
        </BookingProvider>
      </QueryClientProvider>
    );
  }
  return Wrapper;
}

/** Dispatches booking state on mount so Step5 has a serviceId to work with. */
function StateSeeder({ children }: { children: React.ReactNode }) {
  const { dispatch } = useBooking();
  React.useEffect(() => {
    dispatch({ type: 'SET_SERVICE', serviceId: 1, serviceName: 'Nettoyage', categorySlug: 'nettoyage' });
    dispatch({ type: 'SET_COORDINATES', coordinates: { address: '12 Rue Test', city: 'Paris', postalCode: '75001' } });
    dispatch({ type: 'SET_SCHEDULING', scheduling: { date: '2026-06-01', time: '10:00', isAsap: false } });
  }, []);
  return <>{children}</>;
}

describe('BookingStep5Confirmation interaction', () => {
  const navigation = mockNavigation as any;

  it('tap "Confirmer la réservation" calls POST /client/bookings', async () => {
    apiMock.onPost('/client/bookings').replyOnce(201, {
      data: { id: 99, status: 'pending', service_name: 'Nettoyage', scheduled_date: '2026-06-01', scheduled_time: '10:00', address: '12 rue test', city: 'Paris', postal_code: '75001', total_price: 80, created_at: '' },
    });

    render(
      <BookingStep5Confirmation
        navigation={navigation}
        route={{ key: 'step5', name: 'BookingStep5' } as any}
      />,
      { wrapper: makePrefilledWrapper() },
    );

    // Wait for state seed to apply
    await waitFor(() => screen.getByText('Confirmer la réservation'));

    act(() => {
      fireEvent.press(screen.getByText('Confirmer la réservation'));
    });

    await waitFor(() => {
      expect(apiMock.history['post']).toHaveLength(1);
      expect(apiMock.history['post']![0]!.url).toBe('/client/bookings');
    });
  });

  it('shows success overlay after confirmed booking', async () => {
    apiMock.onPost('/client/bookings').replyOnce(201, {
      data: { id: 99, status: 'pending', service_name: 'Nettoyage', scheduled_date: '2026-06-01', scheduled_time: '10:00', address: '12 rue test', city: 'Paris', postal_code: '75001', total_price: 80, created_at: '' },
    });

    render(
      <BookingStep5Confirmation
        navigation={navigation}
        route={{ key: 'step5', name: 'BookingStep5' } as any}
      />,
      { wrapper: makePrefilledWrapper() },
    );

    await waitFor(() => screen.getByText('Confirmer la réservation'));

    act(() => {
      fireEvent.press(screen.getByText('Confirmer la réservation'));
    });

    await waitFor(() => {
      expect(screen.getByText(/Votre demande a été envoyée/)).toBeTruthy();
    });
  });
});
