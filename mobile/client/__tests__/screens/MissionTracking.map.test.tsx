/**
 * L'écran de suivi ne doit jamais planter faute de clé cartographique.
 *
 * Il importait `react-native-maps` statiquement et montait `MapView` sans condition. Sur Android,
 * sans clé Google Maps dans le manifeste, ce composant ne dégrade pas — il lève
 * `IllegalStateException: API key not found` et emporte l'écran entier. C'est exactement le crash
 * rencontré sur le tableau de bord prestataire, resté intact ici.
 *
 * Ce que ces tests verrouillent : la carte native n'est montée que lorsqu'elle peut l'être, le
 * repli OpenStreetMap prend sa place sinon, et le trajet reste tracé dans les deux cas — sur un
 * écran de suivi, un point seul ne montre rien d'utile.
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

const TRAIL = [
  { latitude: 50.84, longitude: 4.35 },
  { latitude: 50.85, longitude: 4.36 },
];

jest.mock('@/tracking', () => ({
  useTrackingSession: () => ({ data: { status: 'enroute', eta_minutes: 12, distance_km: 3.4 }, isLoading: false }),
  useTrackingTrail: () => ({ data: TRAIL }),
  useLiveTracking: () => ({ position: null, eta: null }),
}));

/** Pilote la garde depuis chaque test : avec ou sans clé cartographique utilisable. */
const mapState: { renderable: boolean } = { renderable: true };

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  const ReactLocal = require('react');

  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    isMapRenderable: () => mapState.renderable,
    // `loadMapModule` rend TOUJOURS le module quand react-native-maps est installé : c'est
    // `isMapRenderable` qui juge la présence d'une clé exploitable. Les faire varier ensemble
    // rendrait ce faux infidèle — et le test resterait vert même si l'écran cessait de consulter
    // la garde, ce qui est précisément le défaut corrigé ici.
    loadMapModule: () => ({
            // Le faux expose `animateToRegion` : c'est l'API que l'écran appelle pour suivre le
            // prestataire, et l'omettre ferait passer le test sans jamais l'exercer.
            MapView: ReactLocal.forwardRef(({ children }: any, ref: any) => {
              ReactLocal.useImperativeHandle(ref, () => ({ animateToRegion: jest.fn() }));

              return <View testID="native-map">{children}</View>;
            }),
            Marker: () => <View testID="native-marker" />,
            Callout: () => <View />,
            Polyline: () => <View testID="native-polyline" />,
    }),
    OsmMap: ({ trail, testID }: any) => (
      <View testID={testID}>
        <Text testID="osm-trail-length">{String(trail?.length ?? 0)}</Text>
      </View>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 400: '#818cf8', 500: '#6366f1' },
    surface: { 200: '#e5e5e5', 500: '#737373', 900: '#171717' },
  },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, xl: 20, '2xl': 24 }, fontWeight: { semibold: '600', bold: '700' } },
  radius: { md: 14, pill: 999 },
  shadows: { md: {}, soft: {} },
}));

import { MissionTrackingScreen } from '@/screens/MissionTrackingScreen';

function renderScreen() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={client}>
      <MissionTrackingScreen
        route={{ params: { bookingId: 42 } } as never}
        navigation={{} as never}
      />
    </QueryClientProvider>,
  );
}

describe('Écran de suivi — carte', () => {
  it('monte la carte native quand elle peut réellement s’afficher', async () => {
    mapState.renderable = true;

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('native-map')).toBeTruthy());
    expect(screen.queryByTestId('mission-tracking-map-osm')).toBeNull();
  });

  /**
   * Le défaut central : sans clé, monter la carte native emportait l'écran. Le repli doit prendre
   * sa place, et la carte native ne doit surtout pas être montée.
   */
  it('bascule sur le repli quand aucune clé n’est utilisable', async () => {
    mapState.renderable = false;

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('mission-tracking-map-osm')).toBeTruthy());
    expect(screen.queryByTestId('native-map')).toBeNull();
  });

  it('trace le trajet sur la carte native', async () => {
    mapState.renderable = true;

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('native-polyline')).toBeTruthy());
  });

  /** Le repli aussi : un écran de suivi sans trajet ne montre rien d'utile. */
  it('trace le trajet sur le repli', async () => {
    mapState.renderable = false;

    renderScreen();

    await waitFor(() => expect(screen.getByTestId('osm-trail-length').props.children).toBe('2'));
  });
});
