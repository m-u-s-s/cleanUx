/**
 * La pastille de la carte d'accueil dit où en est la mission, pas seulement dans combien de temps.
 *
 * Le serveur ramène l'ETA à zéro dès que le prestataire franchit la zone d'arrivée. La pastille
 * l'annonçait tel quel — « Arrivée dans ~0 min » — alors que l'information utile est justement
 * qu'il est là. C'est le statut de la session qui la porte à ce moment, pas le compte à rebours.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

/** Pilote la session servie au composant depuis chaque test. */
const sessionState: { status: string; eta_minutes: number | null } = {
  status: 'enroute',
  eta_minutes: 7,
};

jest.mock('@/tracking', () => ({
  useTrackingSession: () => ({
    data: {
      code: 'TRK-1',
      status: sessionState.status,
      destination: { latitude: 50.8467, longitude: 4.3525 },
      provider: { latitude: 50.8412, longitude: 4.3448 },
      eta_seconds: sessionState.eta_minutes === null ? null : sessionState.eta_minutes * 60,
      eta_minutes: sessionState.eta_minutes,
      arrived_at: null,
      in_mission_at: null,
      last_ping_at: null,
    },
  }),
  useTrackingTrail: () => ({ data: [] }),
  useLiveTracking: () => ({ position: null, eta: null }),
}));

jest.mock('@/ui', () => {
  const { View } = require('react-native');

  return {
    OsmMap: () => <View testID="osm" />,
    // Aucune clé cartographique en test : c'est le repli OpenStreetMap qui est monté, comme sur
    // un appareil sans clé. La pastille est rendue par-dessus, dans les deux cas.
    isMapRenderable: () => false,
    loadMapModule: () => null,
  };
});

jest.mock('@/theme', () => ({
  colors: { surface: { 200: '#e2e8f0' }, mode: { tool: { ink: '#0f172a' } } },
  spacing: { xs: 4, sm: 8, md: 16 },
  typography: { fontSize: { sm: 14 }, fontWeight: { semibold: '600' } },
  radius: { md: 14, pill: 999 },
}));

import { HomeMissionMap } from '@/screens/components/HomeMissionMap';

describe('Pastille de la carte d’accueil', () => {
  it('annonce le temps restant tant que le prestataire est en route', () => {
    sessionState.status = 'enroute';
    sessionState.eta_minutes = 7;

    render(<HomeMissionMap bookingId={7} />);

    expect(screen.getByText('Arrivée dans ~7 min')).toBeTruthy();
  });

  /** Le défaut corrigé : à l'arrivée, l'ETA vaut 0 et « ~0 min » n'apprend rien. */
  it('annonce l’arrivée plutôt qu’un compte à rebours nul', () => {
    sessionState.status = 'arrived';
    sessionState.eta_minutes = 0;

    render(<HomeMissionMap bookingId={7} />);

    expect(screen.getByText('Votre prestataire est arrivé')).toBeTruthy();
    expect(screen.queryByText('Arrivée dans ~0 min')).toBeNull();
  });

  it('annonce l’intervention une fois celle-ci démarrée', () => {
    sessionState.status = 'in_mission';
    sessionState.eta_minutes = 0;

    render(<HomeMissionMap bookingId={7} />);

    expect(screen.getByText('Intervention en cours')).toBeTruthy();
  });

  /** Sans ETA connue et en route, il n'y a rien d'honnête à annoncer. */
  it('n’affiche aucune pastille sans ETA', () => {
    sessionState.status = 'enroute';
    sessionState.eta_minutes = null;

    render(<HomeMissionMap bookingId={7} />);

    expect(screen.getByTestId('home-mission-map')).toBeTruthy();
    expect(screen.queryByText(/Arrivée/)).toBeNull();
  });
});
