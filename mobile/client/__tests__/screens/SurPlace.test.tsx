/**
 * LE SUIVI CONTINUE APRÈS LA SONNETTE — et on peut y aller en appuyant.
 *
 * Deux garanties, et la première est celle que ce dépôt a déjà manquée quatre fois : un écran
 * monté dans un navigateur n'est pas un écran atteignable. Le test PRESSE.
 *
 * La seconde porte sur l'abonnement temps réel du suivi, muet depuis toujours pour deux raisons
 * indépendantes : il écoutait des noms de classes PHP au lieu des noms diffusés, et il s'abonnait
 * au canal de la RÉSERVATION alors que le canal est indexé sur la MISSION. Une correction seule
 * n'aurait rien changé, ce qui est exactement pourquoi le défaut a duré.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const mockNavigate = jest.fn();

const canauxEcoutes: (string | null)[] = [];
const evenementsLies: string[] = [];

jest.mock('@/realtime', () => ({
  useChannel: (nom: string | null, evenements: Record<string, unknown>) => {
    canauxEcoutes.push(nom);
    evenementsLies.push(...Object.keys(evenements));
  },
}));

jest.mock('@/tracking', () => {
  const reel = jest.requireActual('@/tracking');

  return {
    ...reel,
    useTrackingSession: () => ({
      data: {
        code: 'TRK-1',
        status: 'in_mission',
        destination: null,
        provider: { latitude: 50.84, longitude: 4.35 },
        eta_seconds: null,
        eta_minutes: 12,
        arrived_at: null,
        in_mission_at: '2026-08-11T09:00:00+00:00',
        last_ping_at: null,
        // Présence confirmée : sans cela l'écran affiche la carte de code à la place de
        // l'encart d'information, et le bouton testé ici n'existe pas.
        presence_confirmed_at: '2026-08-11T09:05:00+00:00',
      },
      isLoading: false,
    }),
    useTrackingTrail: () => ({ data: [] }),
  };
});

jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({
    data: {
      mission_id: 4242,
      status: 'started',
      started_at: '2026-08-11T09:05:00+00:00',
      estimated_end_at: '2026-08-11T11:05:00+00:00',
      progress: { done: 2, total: 5, percent: 40 },
      entries: [],
    },
    isLoading: false,
  }),
}));

jest.mock('@/screens/components/PresenceCodeCard', () => {
  const { View } = require('react-native');

  return { PresenceCodeCard: () => <View /> };
});

import { MissionTrackingScreen } from '@/screens/MissionTrackingScreen';

const route = { params: { bookingId: 77 } } as never;
const navigation = { navigate: mockNavigate } as never;

describe('Suivi de l’intervention chez le client', () => {
  beforeEach(() => {
    mockNavigate.mockClear();
    canauxEcoutes.length = 0;
    evenementsLies.length = 0;
  });

  it('mène au déroulé de l’intervention en appuyant', () => {
    render(<MissionTrackingScreen route={route} navigation={navigation} />);

    fireEvent.press(screen.getByText('Voir le déroulé de l’intervention'));

    expect(mockNavigate).toHaveBeenCalledWith('OnSite', { bookingId: 77 });
  });

  /** Le canal porte le numéro de MISSION, jamais celui de la réservation. */
  it('écoute le canal de la mission, pas celui de la réservation', () => {
    render(<MissionTrackingScreen route={route} navigation={navigation} />);

    expect(canauxEcoutes).toContain('private-mission.4242');
    expect(canauxEcoutes).not.toContain('private-mission.77');
  });

  /** Les noms diffusés par `broadcastAs()`, pas ceux des classes PHP. */
  it('lie les événements que le serveur diffuse réellement', () => {
    render(<MissionTrackingScreen route={route} navigation={navigation} />);

    expect(evenementsLies).toContain('mission.position');
    expect(evenementsLies).toContain('mission.eta');
    expect(evenementsLies).not.toContain('MissionLivePosition');
  });
});
