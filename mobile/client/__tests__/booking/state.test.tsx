import React from 'react';
import { BookingProvider, useBooking } from '@/booking';
import { renderHook, act } from '@testing-library/react-native';

const wrapper = ({ children }: { children: React.ReactNode }) => (
  <BookingProvider>{children}</BookingProvider>
);

describe('BookingProvider', () => {
  it('initializes with empty state', () => {
    const { result } = renderHook(() => useBooking(), { wrapper });
    expect(result.current.state.serviceId).toBeNull();
    expect(result.current.state.details.options).toEqual([]);
  });

  it('SET_SERVICE updates service fields', () => {
    const { result } = renderHook(() => useBooking(), { wrapper });
    act(() =>
      result.current.dispatch({ type: 'SET_SERVICE', serviceId: 1, serviceName: 'Nettoyage', categorySlug: 'cleaning' })
    );
    expect(result.current.state.serviceId).toBe(1);
    expect(result.current.state.serviceName).toBe('Nettoyage');
  });

  it('RESET returns to initial state', () => {
    const { result } = renderHook(() => useBooking(), { wrapper });
    act(() =>
      result.current.dispatch({ type: 'SET_SERVICE', serviceId: 1, serviceName: 'X', categorySlug: 'y' })
    );
    act(() => result.current.dispatch({ type: 'RESET' }));
    expect(result.current.state.serviceId).toBeNull();
  });
});
