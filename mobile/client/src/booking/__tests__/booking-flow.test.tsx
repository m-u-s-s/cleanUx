/**
 * Tests for the booking flow — hooks (useCreateBooking, useBookings, useBookingDetail)
 * and the BookingProvider/useBooking context.
 */
import React from 'react';
import { Text } from 'react-native';
import { render, screen, act, waitFor, renderHook } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

import { apiClient } from '@/api';
import { useCreateBooking, useBookings, useBookingDetail, useEligibleCompanies } from '../hooks';
import { BookingProvider, useBooking } from '../BookingProvider';
import type { Booking, BookingAction } from '../types';

const mock = new MockAdapter(apiClient);

function queryWrapper({ children }: { children: React.ReactNode }) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  return React.createElement(QueryClientProvider, { client }, children);
}

const MOCK_BOOKING: Booking = {
  id: 42,
  status: 'confirmed',
  service_name: 'Nettoyage domicile',
  scheduled_date: '2025-06-01',
  scheduled_time: '10:00',
  address: '12 rue de la Paix',
  city: 'Paris',
  postal_code: '75001',
  total_price: 120,
  created_at: '2025-01-01T00:00:00Z',
};

const BOOKING_INPUT = {
  serviceId: 1,
  details: { options: [], comment: '' },
  coordinates: { address: '12 rue de la Paix', city: 'Paris', postalCode: '75001' },
  scheduling: { date: '2025-06-01', time: '10:00', isAsap: false },
};

beforeEach(() => {
  mock.reset();
  jest.clearAllMocks();
});

describe('useCreateBooking', () => {
  it('returns the created booking on success', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: MOCK_BOOKING });

    const { result } = renderHook(() => useCreateBooking(), { wrapper: queryWrapper });

    act(() => { result.current.mutate(BOOKING_INPUT); });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.id).toBe(42);
    expect(result.current.data?.status).toBe('confirmed');
  });

  it('exposes ApiError on booking creation failure', async () => {
    mock.onPost('/client/bookings').replyOnce(422, {
      ok: false,
      error_code: 'slot_unavailable',
      message: 'The requested slot is no longer available.',
    });

    const { result } = renderHook(() => useCreateBooking(), { wrapper: queryWrapper });

    act(() => { result.current.mutate(BOOKING_INPUT); });

    await waitFor(() => expect(result.current.isError).toBe(true));

    expect(result.current.error?.errorCode).toBe('slot_unavailable');
  });

  it('sends service_catalog_id and scheduling fields in request body', async () => {
    mock.onPost('/client/bookings').replyOnce(200, { data: MOCK_BOOKING });

    const { result } = renderHook(() => useCreateBooking(), { wrapper: queryWrapper });

    act(() => { result.current.mutate(BOOKING_INPUT); });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    const body = JSON.parse(mock.history['post']![0]!.data as string) as Record<string, unknown>;
    expect(body['service_catalog_id']).toBe(1);
    expect(body['scheduled_date']).toBe('2025-06-01');
    expect(body['city']).toBe('Paris');
  });
});

describe('useBookings', () => {
  it('returns the list of bookings', async () => {
    mock.onGet('/client/bookings').replyOnce(200, { data: [MOCK_BOOKING] });

    const { result } = renderHook(() => useBookings(), { wrapper: queryWrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data).toHaveLength(1);
    expect(result.current.data![0]!.id).toBe(42);
  });

  it('handles empty list gracefully', async () => {
    mock.onGet('/client/bookings').replyOnce(200, { data: [] });

    const { result } = renderHook(() => useBookings(), { wrapper: queryWrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data).toHaveLength(0);
  });
});

describe('useBookingDetail', () => {
  it('fetches a single booking by id', async () => {
    mock.onGet('/client/bookings/42').replyOnce(200, { data: MOCK_BOOKING });

    const { result } = renderHook(() => useBookingDetail(42), { wrapper: queryWrapper });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(result.current.data?.service_name).toBe('Nettoyage domicile');
  });

  it('does not fetch when id is null', () => {
    const { result } = renderHook(() => useBookingDetail(null), { wrapper: queryWrapper });
    expect(result.current.fetchStatus).toBe('idle');
    expect(result.current.data).toBeUndefined();
  });
});

describe('useEligibleCompanies', () => {
  it('sends postal_code when only a postal code is provided', async () => {
    mock.onGet('/client/companies').replyOnce(200, { data: [] });

    const { result } = renderHook(
      () => useEligibleCompanies({ postalCode: '1000', serviceCatalogId: 7 }),
      { wrapper: queryWrapper },
    );

    await waitFor(() => expect(result.current.loading).toBe(false));

    const req = mock.history['get']!.find((r) => r.url === '/client/companies');
    expect(req).toBeDefined();
    expect(req!.params).toEqual({ postal_code: '1000', service_catalog_id: 7 });
  });

  it('prefers service_zone_id over postal_code when both are present', async () => {
    mock.onGet('/client/companies').replyOnce(200, { data: [] });

    const { result } = renderHook(
      () => useEligibleCompanies({ serviceZoneId: 3, postalCode: '1000' }),
      { wrapper: queryWrapper },
    );

    await waitFor(() => expect(result.current.loading).toBe(false));

    const req = mock.history['get']!.find((r) => r.url === '/client/companies');
    expect(req!.params).toEqual({ service_zone_id: 3 });
  });

  it('stays idle (no request) when neither zone id nor postal is provided', () => {
    const { result } = renderHook(() => useEligibleCompanies({}), { wrapper: queryWrapper });
    expect(result.current.companies).toEqual([]);
    expect(mock.history['get']!.filter((r) => r.url === '/client/companies')).toHaveLength(0);
  });
});

describe('BookingProvider / useBooking context', () => {
  function ConsumerComponent({ actions }: { actions: BookingAction[] }) {
    const { state, dispatch } = useBooking();
    React.useEffect(() => {
      actions.forEach((a) => dispatch(a));
    }, []);
    return (
      <Text testID="output">
        {state.serviceId ?? 'null'}|{state.serviceName}|{state.scheduling.date}
      </Text>
    );
  }

  it('provides default empty state', () => {
    render(
      <BookingProvider>
        <ConsumerComponent actions={[]} />
      </BookingProvider>,
    );
    expect(screen.getByTestId('output').props.children.join('')).toBe('null||');
  });

  it('SET_SERVICE action updates serviceId and serviceName', async () => {
    render(
      <BookingProvider>
        <ConsumerComponent
          actions={[{ type: 'SET_SERVICE', serviceId: 5, serviceName: 'Peinture', categorySlug: 'peinture' }]}
        />
      </BookingProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('output').props.children.join('')).toBe('5|Peinture|'),
    );
  });

  it('RESET action restores initial state', async () => {
    function ResetConsumer() {
      const { state, dispatch } = useBooking();
      React.useEffect(() => {
        dispatch({ type: 'SET_SERVICE', serviceId: 9, serviceName: 'Test', categorySlug: 'test' });
        dispatch({ type: 'RESET' });
      }, []);
      return <Text testID="output">{state.serviceId ?? 'null'}</Text>;
    }

    render(
      <BookingProvider>
        <ResetConsumer />
      </BookingProvider>,
    );

    await waitFor(() =>
      expect(screen.getByTestId('output').props.children).toBe('null'),
    );
  });
});
