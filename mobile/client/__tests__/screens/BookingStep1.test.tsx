import React from 'react';
import { BookingStep1Service } from '@/screens/booking/BookingStep1Service';
import { BookingStep2Details } from '@/screens/booking/BookingStep2Details';
import { BookingStep3Coordinates } from '@/screens/booking/BookingStep3Coordinates';
import { BookingStep4Scheduling } from '@/screens/booking/BookingStep4Scheduling';
import { BookingStep5Confirmation } from '@/screens/booking/BookingStep5Confirmation';

jest.mock('@/storage/secureStore');

describe('Booking step screens — import sanity', () => {
  it('BookingStep1Service is defined', () => {
    expect(BookingStep1Service).toBeDefined();
    expect(typeof BookingStep1Service).toBe('function');
  });

  it('BookingStep2Details is defined', () => {
    expect(BookingStep2Details).toBeDefined();
    expect(typeof BookingStep2Details).toBe('function');
  });

  it('BookingStep3Coordinates is defined', () => {
    expect(BookingStep3Coordinates).toBeDefined();
    expect(typeof BookingStep3Coordinates).toBe('function');
  });

  it('BookingStep4Scheduling is defined', () => {
    expect(BookingStep4Scheduling).toBeDefined();
    expect(typeof BookingStep4Scheduling).toBe('function');
  });

  it('BookingStep5Confirmation is defined', () => {
    expect(BookingStep5Confirmation).toBeDefined();
    expect(typeof BookingStep5Confirmation).toBe('function');
  });
});
