import React from 'react';
import { render } from '@testing-library/react-native';
import { OfferModal } from '../OfferModal';
import type { MissionOffer } from '../types';

jest.mock('../hooks', () => ({
  __esModule: true,
  useAcceptOffer: () => ({ mutate: jest.fn(), isPending: false }),
  useDeclineOffer: () => ({ mutate: jest.fn(), isPending: false }),
  useServerCountdown: (expiresAt?: string | null) =>
    expiresAt ? 18 : 0,
}));

jest.mock('@react-navigation/native', () => ({
  __esModule: true,
  useNavigation: () => ({ navigate: jest.fn() }),
}));

const offre = (expiresAt: string | null): MissionOffer =>
  ({
    assignment_id: 8,
    mission_id: 6,
    booking_id: 6,
    booking_mode: 'scheduled',
    trade_name: 'Nettoyage à domicile',
    service_name: null,
    client_name: null,
    approximate_address: null,
    city: 'Anvers',
    postal_code: '2000',
    scheduled_at: null,
    estimated_duration_minutes: 120,
    payout_cents: 9900,
    distance_m: null,
    distance_km: null,
    latitude: null,
    longitude: null,
    expires_at: expiresAt,
    ttl_seconds: expiresAt ? 20 : null,
    sent_at: null,
  }) as MissionOffer;

/**
 * « 0 s POUR RÉPONDRE » SUR UNE OFFRE VIVANTE.
 *
 * `useServerCountdown` rend 0 quand le serveur n'envoie pas d'`expires_at` — et toutes les
 * affectations posées hors du chemin marketplace n'en portent pas. L'anneau annonçait donc zéro
 * seconde sur une offre que l'on pouvait accepter : mesuré sur l'émulateur le 2026-09-06,
 * affectation acceptée à 21h44 pendant que l'écran affichait « 0 s ».
 */
describe('OfferModal — le décompte', () => {
  it('affiche le décompte quand le serveur a envoyé une échéance', () => {
    const { getByTestId } = render(
      <OfferModal offer={offre('2026-09-06T21:46:42+00:00')} onDismiss={jest.fn()} />,
    );

    expect(getByTestId('offer-countdown')).toBeTruthy();
  });

  it('n’affiche aucun décompte quand il n’y a pas d’échéance', () => {
    const { queryByTestId } = render(<OfferModal offer={offre(null)} onDismiss={jest.fn()} />);

    expect(queryByTestId('offer-countdown')).toBeNull();
  });

  it('témoin : l’offre reste montrée et acceptable sans échéance', () => {
    const { getByTestId } = render(<OfferModal offer={offre(null)} onDismiss={jest.fn()} />);

    expect(getByTestId('offer-modal')).toBeTruthy();
    expect(getByTestId('offer-trade')).toBeTruthy();
  });
});
