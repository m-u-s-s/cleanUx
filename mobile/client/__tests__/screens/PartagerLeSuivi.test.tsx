import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';
import { Share } from 'react-native';

/**
 * « SUIVEZ L'ARRIVÉE DU PRESTATAIRE » — le lien qui existait pour personne.
 *
 * Le lien signé, sa validité de douze heures et la page publique volontairement pauvre sont en
 * place depuis longtemps, et le web les expose. Sur mobile, `POST /tracking/share` n'avait AUCUN
 * appelant : la cinquième fonction complète et injoignable de ce dépôt.
 *
 * Ce test PRESSE le bouton et vérifie que la feuille de partage native reçoit bien le lien — un
 * bouton monté qui n'ouvre rien serait exactement le même défaut, déplacé d'un cran.
 */

const mockPartager = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/realtime', () => ({ useChannel: () => undefined }));

jest.mock('@/tracking', () => ({
  useTrackingSession: () => ({ data: null, isLoading: false }),
  useTrackingTrail: () => ({ data: [] }),
  useLiveTracking: () => ({ position: null, eta: null }),
  usePartagerLeSuivi: () => ({ mutate: mockPartager, isPending: false }),
}));

jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({ data: { mission_id: 4242 }, isLoading: false }),
  useRetard: () => ({ data: { en_retard: false, minutes: null, annonce: null, annulation_gratuite: false, prevenu_at: null } }),
  useReprogrammer: () => ({ mutate: jest.fn(), isPending: false }),
}));

jest.mock('@brio/shared', () => {
  const { View } = require('react-native');

  return { AnnulerLaMissionSheet: () => <View /> };
});

jest.mock('@/screens/components/MissionSheet', () => {
  const { View } = require('react-native');
  const ReactLocal = require('react');

  return { MissionSheet: ReactLocal.forwardRef(() => <View />) };
});

jest.mock('@/screens/components/PresenceCodeCard', () => {
  const { View } = require('react-native');

  return { PresenceCodeCard: () => <View /> };
});

import { MissionTrackingScreen } from '@/screens/MissionTrackingScreen';

const route = { params: { bookingId: 77 } } as never;
const navigation = { navigate: jest.fn() } as never;

describe('Partager le suivi avec un tiers', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('offre le bouton sous la porte d’entrée', () => {
    render(<MissionTrackingScreen route={route} navigation={navigation} />);

    expect(screen.getByTestId('partager-le-suivi')).toBeTruthy();
  });

  it('ouvre la feuille de partage avec le lien et sa durée', () => {
    const feuille = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction' } as never);

    // Le crochet est bouchonné : on rejoue son succès pour vérifier ce que l'écran en fait.
    mockPartager.mockImplementation((_: unknown, options: { onSuccess: (d: unknown) => void }) =>
      options.onSuccess({ url: 'https://exemple.test/suivi/abc', expires_in_hours: 12 }),
    );

    render(<MissionTrackingScreen route={route} navigation={navigation} />);
    fireEvent.press(screen.getByTestId('partager-le-suivi'));

    expect(feuille).toHaveBeenCalledTimes(1);
    expect(feuille.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({ message: expect.stringContaining('https://exemple.test/suivi/abc') }),
    );
    expect(feuille.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({ message: expect.stringContaining('12 h') }),
    );

    feuille.mockRestore();
  });
});
