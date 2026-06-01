/**
 * SP2 Task 7 — Booking wizard provider-selection step (mobile).
 *
 * Covers parity with web Task 6:
 *  - selecting a TYPE updates the wizard state (providerTypePreference);
 *  - the create payload includes provider_type_preference (+ preferred_provider_user_id);
 *  - the premium "Choisir un prestataire" block shows only when the premium flag is
 *    true; otherwise an upsell encart is shown;
 *  - re-booking a FAVOURITE sets preferredProviderUserId.
 */
import React from 'react';
import { Text } from 'react-native';
import {
  render,
  screen,
  fireEvent,
  waitFor,
  renderHook,
  act,
} from '@testing-library/react-native';
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

// Premium flag is read from the auth user. Toggle via this mutable holder.
const authState: { user: { id: number; is_premium?: boolean } | null } = { user: { id: 1 } };
jest.mock('@/auth', () => ({
  useAuth: () => ({ user: authState.user, isAuthenticated: true, isLoading: false }),
}));

// ── Imports (after mocks) ─────────────────────────────────────────────────────

import { apiClient } from '@/api';
import { BookingProvider, useBooking, useCreateBooking } from '@/booking';
import { BookingStepProvider } from '../BookingStepProvider';

const mock = new MockAdapter(apiClient);

function makeClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
}

function navStub() {
  return {
    navigate: jest.fn(),
    getParent: jest.fn(() => ({ dispatch: jest.fn() })),
  } as never;
}

// Probe that surfaces the wizard state so the test can assert it.
function StateProbe() {
  const { state } = useBooking();
  return (
    <Text testID="probe">
      {state.providerTypePreference}|{state.preferredProviderUserId ?? 'null'}
    </Text>
  );
}

function renderStep() {
  const client = makeClient();
  return render(
    <QueryClientProvider client={client}>
      <BookingProvider>
        <StateProbe />
        <BookingStepProvider navigation={navStub()} route={{ key: 'k', name: 'BookingStepProvider' } as never} />
      </BookingProvider>
    </QueryClientProvider>,
  );
}

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
  authState.user = { id: 1 };
  // Default: no favourites unless a test overrides.
  mock.onGet('/client/favorites').reply(200, { data: [] });
});

describe('BookingStepProvider — type selector', () => {
  it('defaults to "any" and updates state when a type is selected', async () => {
    renderStep();

    expect(screen.getByTestId('probe').props.children.join('')).toBe('any|null');

    fireEvent.press(screen.getByTestId('provider-type-independent'));

    await waitFor(() =>
      expect(screen.getByTestId('probe').props.children.join('')).toBe('independent|null'),
    );

    fireEvent.press(screen.getByTestId('provider-type-company'));
    await waitFor(() =>
      expect(screen.getByTestId('probe').props.children.join('')).toBe('company|null'),
    );
  });
});

describe('BookingStepProvider — premium gating', () => {
  it('shows the upsell encart and hides the premium pick when not premium', async () => {
    authState.user = { id: 1, is_premium: false };
    renderStep();

    await waitFor(() => expect(screen.getByTestId('premium-upsell')).toBeTruthy());
    expect(screen.queryByText('Choisir un prestataire')).toBeNull();
  });

  it('shows the premium pick and hides the upsell when premium', async () => {
    authState.user = { id: 1, is_premium: true };
    renderStep();

    await waitFor(() => expect(screen.getByText('Choisir un prestataire')).toBeTruthy());
    expect(screen.queryByTestId('premium-upsell')).toBeNull();
  });
});

describe('BookingStepProvider — favourites re-book', () => {
  it('sets preferredProviderUserId when a favourite is re-booked', async () => {
    mock.reset();
    mock.onGet('/client/favorites').reply(200, {
      data: [
        { id: 7, label: 'Marie (super)', preferred_provider: { id: 99, name: 'Marie' } },
      ],
    });

    renderStep();

    await waitFor(() => expect(screen.getByTestId('favorite-7')).toBeTruthy());

    fireEvent.press(screen.getByTestId('favorite-7'));

    await waitFor(() =>
      expect(screen.getByTestId('probe').props.children.join('')).toBe('any|99'),
    );
  });
});

describe('useCreateBooking — SP2 payload', () => {
  const BASE_INPUT = {
    serviceId: 1,
    details: { options: [], comment: '' },
    coordinates: { address: '12 rue', city: 'Paris', postalCode: '75001' },
    scheduling: { date: '2099-06-01', time: '10:00', isAsap: false },
  };

  function wrapper({ children }: { children: React.ReactNode }) {
    return React.createElement(QueryClientProvider, { client: makeClient() }, children);
  }

  it('sends provider_type_preference and preferred_provider_user_id in the body', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: { id: 1, status: 'en_attente' } });

    const { result } = renderHook(() => useCreateBooking(), { wrapper });

    act(() => {
      result.current.mutate({
        ...BASE_INPUT,
        providerTypePreference: 'company',
        preferredProviderUserId: 99,
      });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['provider_type_preference']).toBe('company');
    expect(body['preferred_provider_user_id']).toBe(99);
  });

  it('omits the preferred provider (null) for an auto-match booking', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: { id: 2, status: 'en_attente' } });

    const { result } = renderHook(() => useCreateBooking(), { wrapper });

    act(() => {
      result.current.mutate({
        ...BASE_INPUT,
        providerTypePreference: 'any',
        preferredProviderUserId: null,
      });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['provider_type_preference']).toBe('any');
    expect(body['preferred_provider_user_id']).toBeNull();
  });
});
