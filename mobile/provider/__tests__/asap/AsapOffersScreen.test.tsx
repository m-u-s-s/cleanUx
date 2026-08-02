import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';

jest.mock('@/storage/secureStore');
jest.mock('@/realtime', () => ({ useChannel: () => {} }));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

const mockAccept = jest.fn();
const mockDecline = jest.fn();
let mockOffers: any[] = [];
let mockIsLoading = false;

jest.mock('@/asap/hooks', () => ({
  ASAP_REFETCH_INTERVAL_MS: 5000,
  useAsapOffers: () => ({ offers: mockOffers, isLoading: mockIsLoading, refetchIntervalMs: 5000 }),
  useAcceptAsapOffer: () => ({ mutate: mockAccept, isPending: false }),
  useDeclineAsapOffer: () => ({ mutate: mockDecline, isPending: false }),
}));

import { AsapOffersScreen } from '@/asap/AsapOffersScreen';

const OFFER = {
  id: 7,
  asap_dispatch_request_id: 42,
  trade: 'Plomberie',
  distance_m: 2300,
  distance_km: 2.3,
  estimate_min_cents: 8500,
  estimate_max_cents: 12000,
  notified_at: '2026-08-02T10:00:00+00:00',
  waiting_seconds: 45,
};

/**
 * Trois secondes pour décider.
 *
 * Un prestataire qui reçoit une course immédiate est souvent au volant ou sur un chantier. Ce que
 * l'écran doit lui donner tient en quatre choses — le métier, la distance, le montant, l'attente —
 * et deux boutons. Tout le reste lui coûte la course.
 */
describe('Écran des courses immédiates', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockOffers = [OFFER];
    mockIsLoading = false;
  });

  it('montre le métier, la distance et le montant', () => {
    render(<AsapOffersScreen />);

    expect(screen.getByText('Plomberie')).toBeTruthy();
    expect(screen.getByText(/2,3 km/)).toBeTruthy();
    expect(screen.getByText(/85/)).toBeTruthy();
  });

  it('accepte la course', () => {
    render(<AsapOffersScreen />);

    fireEvent.press(screen.getByText('Accepter'));

    expect(mockAccept).toHaveBeenCalledWith(42, expect.anything());
  });

  /**
   * Après acceptation, on va à la LISTE des missions.
   *
   * L'acceptation rend un `booking_id` ; `MissionDetail` attend un `missionId`, qui est l'id d'un
   * modèle Mission — deux identifiants différents. Les confondre ouvrirait le détail d'une autre
   * intervention, ou de rien : c'est le défaut que signale déjà le commentaire de l'écran d'offre
   * voisin.
   */
  it('envoie vers la liste des missions, jamais vers un détail au mauvais identifiant', () => {
    mockAccept.mockImplementation((_id: number, opts: any) => {
      opts.onSuccess({ booking_id: 99 });
    });

    render(<AsapOffersScreen />);
    fireEvent.press(screen.getByText('Accepter'));

    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Missions' });
    expect(mockNavigate).not.toHaveBeenCalledWith('MissionDetail', expect.anything());
  });

  it('passe son tour', () => {
    render(<AsapOffersScreen />);

    fireEvent.press(screen.getByText('Passer'));

    expect(mockDecline).toHaveBeenCalledWith({ requestId: 42 }, expect.anything());
  });

  /**
   * L'écran vide est une INVITATION, pas un constat d'échec.
   *
   * Un prestataire qui ouvre l'écran entre deux courses doit comprendre qu'il est en attente, pas
   * qu'il y a un problème.
   */
  it('dit ce qui se passe quand rien n’est proposé', () => {
    mockOffers = [];
    render(<AsapOffersScreen />);

    expect(screen.getByText(/Aucune course/i)).toBeTruthy();
  });

  /** Une course déjà prise se dit en une phrase, pas en « une erreur est survenue ». */
  it('explique qu’une course vient d’être prise', async () => {
    mockAccept.mockImplementation((_id: number, opts: any) => {
      opts.onError({ status: 409, message: 'déjà prise' });
    });

    render(<AsapOffersScreen />);
    fireEvent.press(screen.getByText('Accepter'));

    await waitFor(() => expect(screen.getByText(/vient d’être prise/i)).toBeTruthy());
  });
});
