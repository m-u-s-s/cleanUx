import React, { createContext, useContext, useReducer } from 'react';
import type { BookingState, BookingAction } from './types';

const initialState: BookingState = {
  serviceId: null,
  serviceName: '',
  categorySlug: '',
  details: { options: [], comment: '' },
  coordinates: { address: '', city: '', postalCode: '' },
  scheduling: { date: '', time: '', isAsap: false },
};

function bookingReducer(state: BookingState, action: BookingAction): BookingState {
  switch (action.type) {
    case 'SET_SERVICE':
      return { ...state, serviceId: action.serviceId, serviceName: action.serviceName, categorySlug: action.categorySlug };
    case 'SET_DETAILS':
      return { ...state, details: action.details };
    case 'SET_COORDINATES':
      return { ...state, coordinates: action.coordinates };
    case 'SET_SCHEDULING':
      return { ...state, scheduling: action.scheduling };
    case 'RESET':
      return initialState;
    default:
      return state;
  }
}

const BookingContext = createContext<{ state: BookingState; dispatch: React.Dispatch<BookingAction> }>({
  state: initialState,
  dispatch: () => {},
});

export function BookingProvider({ children }: { children: React.ReactNode }) {
  const [state, dispatch] = useReducer(bookingReducer, initialState);
  return <BookingContext.Provider value={{ state, dispatch }}>{children}</BookingContext.Provider>;
}

export function useBooking() {
  return useContext(BookingContext);
}
