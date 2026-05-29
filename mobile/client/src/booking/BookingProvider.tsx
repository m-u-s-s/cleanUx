import React, { createContext, useContext, useReducer, useEffect, useRef } from 'react';
import { bookingDraft } from '@/storage/bookingDraft';
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
      void bookingDraft.clear();
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
  const isInitialised = useRef(false);

  // Restore draft on mount
  useEffect(() => {
    bookingDraft.load().then((saved) => {
      if (saved) {
        const s = saved as unknown as BookingState;
        if (s.serviceId !== null) {
          dispatch({ type: 'SET_SERVICE', serviceId: s.serviceId, serviceName: s.serviceName, categorySlug: s.categorySlug });
          dispatch({ type: 'SET_DETAILS', details: s.details });
          dispatch({ type: 'SET_COORDINATES', coordinates: s.coordinates });
          dispatch({ type: 'SET_SCHEDULING', scheduling: s.scheduling });
        }
      }
      isInitialised.current = true;
    }).catch(() => { isInitialised.current = true; });
  }, []);

  // Persist draft on every state change after initialisation
  useEffect(() => {
    if (!isInitialised.current) return;
    if (state.serviceId === null) return; // do not persist empty draft
    void bookingDraft.save(state as unknown as Record<string, unknown>);
  }, [state]);

  return <BookingContext.Provider value={{ state, dispatch }}>{children}</BookingContext.Provider>;
}

export function useBooking() {
  return useContext(BookingContext);
}
