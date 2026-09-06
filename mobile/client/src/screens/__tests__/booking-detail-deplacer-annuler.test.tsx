import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { BookingDetailScreen } from '../BookingDetailScreen';
import * as booking from '@/booking';
import * as onsite from '@/booking/onsite';

jest.mock('@/booking', () => ({ __esModule: true, useBookingDetail: jest.fn() }));

jest.mock('@/booking/onsite', () => ({
  __esModule: true,
  useReprogrammer: jest.fn(),
}));

jest.mock('@react-navigation/native', () => ({
  __esModule: true,
  useNavigation: () => ({ navigate: jest.fn() }),
}));

jest.mock('@/tracking', () => ({
  __esModule: true,
  useCompletionCode: () => ({ mutate: jest.fn(), isPending: false }),
}));

const route: any = { params: { bookingId: 42 } };

const reservation = (etat: string) => ({
  id: 42,
  service_name: 'Nettoyage fin de chantier',
  status: etat,
  state: etat,
  scheduled_date: '2026-09-07',
  scheduled_time: '10:00',
  address: 'Rue de la Loi 16',
  city: null,
  estimated_price: 150,
  currency: 'EUR',
});

const monter = (etat: string) => {
  (booking.useBookingDetail as jest.Mock).mockReturnValue({
    data: reservation(etat),
    isLoading: false,
    isError: false,
    refetch: jest.fn(),
  });

  return render(<BookingDetailScreen route={route} navigation={{} as any} />);
};

/**
 * DÉPLACER ET ANNULER, DEPUIS LE DÉTAIL D'UNE RÉSERVATION.
 *
 * Les deux services existaient — `POST /client/bookings/{id}/reschedule` et `/cancel` — et
 * n'étaient atteignables QUE depuis le suivi de mission, donc une fois le prestataire en route.
 * Sur une demande `en_attente` que personne n'avait acceptée, l'écran n'offrait que « Payer ».
 */
describe('BookingDetailScreen — déplacer et annuler', () => {
  const mutate = jest.fn();

  beforeEach(() => {
    jest.clearAllMocks();
    (onsite.useReprogrammer as jest.Mock).mockReturnValue({ mutate, isPending: false });
  });

  it('offre les deux gestes sur une réservation en attente', () => {
    const { getByTestId } = monter('pending');

    expect(getByTestId('ouvrir-reprogrammation')).toBeTruthy();
    expect(getByTestId('ouvrir-annulation')).toBeTruthy();
  });

  it('témoin : une réservation annulée n’offre ni l’un ni l’autre', () => {
    const { queryByTestId } = monter('cancelled');

    expect(queryByTestId('ouvrir-reprogrammation')).toBeNull();
    expect(queryByTestId('ouvrir-annulation')).toBeNull();
  });

  it('témoin : une prestation terminée ne se déplace plus', () => {
    const { queryByTestId } = monter('completed');

    expect(queryByTestId('ouvrir-reprogrammation')).toBeNull();
  });

  it('déplace au jour choisi, en gardant l’heure', () => {
    const { getByTestId, getAllByTestId } = monter('pending');

    fireEvent.press(getByTestId('ouvrir-reprogrammation'));

    // Les jours proposés sont datés : on prend le premier, quel qu'il soit.
    const jours = getAllByTestId(/^jour-\d{4}-\d{2}-\d{2}$/);
    expect(jours.length).toBeGreaterThan(0);

    fireEvent.press(jours[0]!);
    fireEvent.press(getByTestId('confirmer-reprogrammation'));

    expect(mutate).toHaveBeenCalledTimes(1);
    expect(mutate.mock.calls[0][0]).toEqual(
      expect.objectContaining({ time: '10:00', date: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/) }),
    );
  });

  it('témoin : sans jour choisi, rien n’est envoyé', () => {
    const { getByTestId } = monter('pending');

    fireEvent.press(getByTestId('ouvrir-reprogrammation'));
    fireEvent.press(getByTestId('confirmer-reprogrammation'));

    expect(mutate).not.toHaveBeenCalled();
  });
});
