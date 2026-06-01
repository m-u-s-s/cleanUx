/**
 * SP3 Task 9 (mobile) — premium client selects a provider COMPANY in the wizard.
 *
 * Mirrors the SP2 provider-search test. Three concerns:
 *  1. Reducer: SET_PREFERRED_COMPANY sets assignedProviderOrganizationId and CLEARS
 *     preferredProviderUserId (mutual exclusion, like the web Task 8 reducer); a
 *     null payload clears the company back to null.
 *  2. Hook useEligibleCompanies calls GET /client/companies with service_zone_id
 *     (+ optional service_catalog_id) and returns the list / loading / error.
 *  3. The create payload carries assigned_provider_organization_id when set.
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

// ── Imports (after mocks) ─────────────────────────────────────────────────────

import { apiClient } from '@/api';
import {
  BookingProvider,
  useBooking,
  useEligibleCompanies,
  useCreateBooking,
} from '@/booking';
import type { BookingAction } from '@/booking';
import { BookingCompanySearchScreen } from '../BookingCompanySearchScreen';

const mock = new MockAdapter(apiClient);

function makeClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
}

function navStub() {
  return { navigate: jest.fn(), goBack: jest.fn() } as never;
}

function queryWrapper({ children }: { children: React.ReactNode }) {
  return React.createElement(QueryClientProvider, { client: makeClient() }, children);
}

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

// ── 1. Reducer — mutual exclusion company ↔ worker ─────────────────────────────

describe('booking reducer — SET_PREFERRED_COMPANY', () => {
  function Consumer({ actions }: { actions: BookingAction[] }) {
    const { state, dispatch } = useBooking();
    React.useEffect(() => {
      actions.forEach((a) => dispatch(a));
    }, []);
    return (
      <Text testID="out">
        {state.assignedProviderOrganizationId ?? 'null'}|{state.preferredProviderUserId ?? 'null'}
      </Text>
    );
  }

  it('sets assignedProviderOrganizationId and clears preferredProviderUserId', async () => {
    render(
      <BookingProvider>
        <Consumer
          actions={[
            { type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: 99 },
            { type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: 7 },
          ]}
        />
      </BookingProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('out').props.children.join('')).toBe('7|null'));
  });

  it('picking a worker after a company clears the company (mutual exclusion)', async () => {
    render(
      <BookingProvider>
        <Consumer
          actions={[
            { type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: 7 },
            { type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: 42 },
          ]}
        />
      </BookingProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('out').props.children.join('')).toBe('null|42'));
  });

  it('SET_PREFERRED_COMPANY with null clears the company back to null', async () => {
    render(
      <BookingProvider>
        <Consumer
          actions={[
            { type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: 7 },
            { type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: null },
          ]}
        />
      </BookingProvider>,
    );

    await waitFor(() => expect(screen.getByTestId('out').props.children.join('')).toBe('null|null'));
  });
});

// ── 2. Hook — GET /client/companies ────────────────────────────────────────────

describe('useEligibleCompanies', () => {
  it('calls /client/companies with service_zone_id + service_catalog_id and returns the list', async () => {
    mock.onGet('/client/companies').reply(200, {
      data: [
        { id: 7, name: 'Net Pro SARL', rating_avg: 4.6, rating_count: 22, providers_count: 5 },
      ],
    });

    const { result } = renderHook(
      () => useEligibleCompanies({ serviceZoneId: 3, serviceCatalogId: 11 }),
      { wrapper: queryWrapper },
    );

    await waitFor(() => expect(result.current.companies).toHaveLength(1));

    expect(result.current.companies[0]!.id).toBe(7);
    expect(result.current.companies[0]!.providers_count).toBe(5);
    expect(result.current.error).toBeNull();

    const req = mock.history['get']![0]!;
    expect(req.params).toEqual({ service_zone_id: 3, service_catalog_id: 11 });
  });

  it('does not fire when neither serviceZoneId nor postalCode is provided', () => {
    const { result } = renderHook(() => useEligibleCompanies({}), { wrapper: queryWrapper });
    expect(mock.history['get']?.length ?? 0).toBe(0);
    expect(result.current.companies).toEqual([]);
  });

  it('sends postal_code when the wizard postal code is provided', async () => {
    mock.onGet('/client/companies').reply(200, { data: [] });

    const { result } = renderHook(
      () => useEligibleCompanies({ postalCode: '1000', serviceCatalogId: 11 }),
      { wrapper: queryWrapper },
    );

    await waitFor(() => expect(result.current.loading).toBe(false));

    const req = mock.history['get']![0]!;
    expect(req.params).toEqual({ postal_code: '1000', service_catalog_id: 11 });
  });
});

// ── 3. Create payload carries the org id ───────────────────────────────────────

describe('useCreateBooking — SP3 company payload', () => {
  const BASE_INPUT = {
    serviceId: 1,
    details: { options: [], comment: '' },
    coordinates: { address: '12 rue', city: 'Paris', postalCode: '75001' },
    scheduling: { date: '2099-06-01', time: '10:00', isAsap: false },
  };

  it('sends assigned_provider_organization_id in the body when set', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: { id: 1, status: 'en_attente' } });

    const { result } = renderHook(() => useCreateBooking(), { wrapper: queryWrapper });

    act(() => {
      result.current.mutate({
        ...BASE_INPUT,
        providerTypePreference: 'company',
        assignedProviderOrganizationId: 7,
      });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['assigned_provider_organization_id']).toBe(7);
    expect(body['provider_type_preference']).toBe('company');
  });

  it('defaults assigned_provider_organization_id to null when omitted', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: { id: 2, status: 'en_attente' } });

    const { result } = renderHook(() => useCreateBooking(), { wrapper: queryWrapper });

    act(() => {
      result.current.mutate({ ...BASE_INPUT });
    });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['assigned_provider_organization_id']).toBeNull();
  });
});

// ── 4. Screen — selection returns the pick to the wizard ───────────────────────

describe('BookingCompanySearchScreen — selection returns the pick to the wizard', () => {
  function StateProbe() {
    const { state } = useBooking();
    return <Text testID="probe">{state.assignedProviderOrganizationId ?? 'null'}</Text>;
  }

  function renderSearch(nav: ReturnType<typeof navStub>) {
    return render(
      <QueryClientProvider client={makeClient()}>
        <BookingProvider>
          <StateProbe />
          <BookingCompanySearchScreen
            navigation={nav}
            route={{ key: 'k', name: 'BookingCompanySearch', params: { serviceZoneId: 3, serviceCatalogId: 1 } } as never}
          />
        </BookingProvider>
      </QueryClientProvider>,
    );
  }

  it('dispatches SET_PREFERRED_COMPANY and goes back when a company is chosen', async () => {
    mock.onGet('/client/companies').reply(200, {
      data: [
        { id: 7, name: 'Net Pro SARL', rating_avg: 4.6, rating_count: 22, providers_count: 5 },
      ],
    });

    const nav = navStub();
    renderSearch(nav);

    await waitFor(() => expect(screen.getByTestId('choose-company-7')).toBeTruthy());
    expect(screen.getByTestId('probe').props.children).toBe('null');

    fireEvent.press(screen.getByTestId('choose-company-7'));

    await waitFor(() => expect(screen.getByTestId('probe').props.children).toBe(7));
    expect((nav as unknown as { goBack: jest.Mock }).goBack).toHaveBeenCalledTimes(1);
  });
});
