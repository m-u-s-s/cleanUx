/**
 * SP2 palier 3 (mobile) — premium provider PICK returns to the wizard.
 *
 * The booking provider search screen lives inside the BookingNavigator stack and
 * therefore shares the wizard's BookingProvider context. When the client taps
 * "Choisir" on a provider, the screen dispatches SET_PREFERRED_PROVIDER (with the
 * provider's user id) and goes back — so the wizard's preferredProviderUserId is
 * updated. This test exercises that selection flow end-to-end through context.
 */
import React from 'react';
import { Text } from 'react-native';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
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
  fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true, isInternetReachable: true }),
  },
}));

// ── Imports (after mocks) ─────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { BookingProvider, useBooking } from '@/booking';
import { BookingProviderSearchScreen } from '../BookingProviderSearchScreen';

const mock = new MockAdapter(apiClient);

function makeClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
}

function navStub() {
  return {
    navigate: jest.fn(),
    goBack: jest.fn(),
  } as never;
}

// Probe that surfaces the wizard state so the test can assert it.
function StateProbe() {
  const { state } = useBooking();
  return <Text testID="probe">{state.preferredProviderUserId ?? 'null'}</Text>;
}

function renderSearch(nav: ReturnType<typeof navStub>) {
  const client = makeClient();
  return render(
    <QueryClientProvider client={client}>
      <BookingProvider>
        <StateProbe />
        <BookingProviderSearchScreen
          navigation={nav}
          route={{ key: 'k', name: 'BookingProviderSearch' } as never}
        />
      </BookingProvider>
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

describe('BookingProviderSearchScreen — selection returns the pick to the wizard', () => {
  it('dispatches SET_PREFERRED_PROVIDER and goes back when a provider is chosen', async () => {
    // /search/providers returns the provider USER id as `id`.
    mock.onGet('/search/providers').reply(200, {
      data: [
        {
          id: 42,
          name: 'Atelier Propre',
          rating_avg: 4.8,
          review_count: 31,
          trades: ['nettoyage'],
        },
      ],
    });

    const nav = navStub();
    renderSearch(nav);

    // Type a filter so useBrowseProviders is enabled and fires the request.
    fireEvent.changeText(screen.getByPlaceholderText('nettoyage, peinture…'), 'nettoyage');

    await waitFor(() => expect(screen.getByTestId('choose-provider-42')).toBeTruthy());

    expect(screen.getByTestId('probe').props.children).toBe('null');

    fireEvent.press(screen.getByTestId('choose-provider-42'));

    // Wizard context now holds the picked provider id …
    await waitFor(() => expect(screen.getByTestId('probe').props.children).toBe(42));
    // … and we returned to the wizard.
    expect((nav as unknown as { goBack: jest.Mock }).goBack).toHaveBeenCalledTimes(1);
  });
});
