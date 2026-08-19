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

// Forme réelle des points, telle que la produisent les crochets : le serveur envoie `lat`/`lng`
// et `distance_to_dest_m`, la traduction vers le vocabulaire des cartes se fait dans `@/tracking`.
const TRAIL = [
  { latitude: 50.84, longitude: 4.35, eta_seconds: 900, distance_to_dest_m: 3400, recorded_at: '2026-07-30T08:00:00Z' },
  { latitude: 50.85, longitude: 4.36, eta_seconds: 720, distance_to_dest_m: 2600, recorded_at: '2026-07-30T08:05:00Z' },
];

// La session ne porte PAS de distance — elle est relevée point par point. Un faux qui en
// inventerait une validerait un champ que le serveur n'envoie pas.
/*
 * Le fil « sur place » est faux ici : cet écran l'interroge désormais pour connaître le NUMÉRO DE
 * MISSION, seul identifiant que le canal temps réel accepte. Le laisser réel ferait partir une
 * requête au serveur au milieu d'un test de carte.
 */
jest.mock('@/booking/onsite', () => ({
  useOnSiteTimeline: () => ({ data: { mission_id: 4242 }, isLoading: false }),
  // Pas de retard : ce test regarde la carte, pas le minuteur.
  useRetard: () => ({ data: { en_retard: false, minutes: null, annonce: null, annulation_gratuite: false, prevenu_at: null } }),
  useReprogrammer: () => ({ mutate: jest.fn(), isPending: false }),
}));

/*
 * LA FEUILLE D'ANNULATION EST BOUCHONNEE, pour la meme raison que `MissionSheet` ci-dessous.
 *
 * Elle vient de `@brio/shared`, dont le tonneau charge tout le systeme de composants — et ce
 * fichier bouchonne `@/theme` avec une palette PARTIELLE. Le vrai bouton y lirait une couleur
 * absente et le test echouerait sur le chargement du module, sans rien dire de la carte.
 */
jest.mock('@brio/shared', () => {
  const { View } = require('react-native');

  return { AnnulerLaMissionSheet: () => <View /> };
});

/*
 * LA FEUILLE EST BOUCHONNÉE, comme l'accueil bouchonne la sienne.
 *
 * Elle s'appuie sur `@gorhom/bottom-sheet`, dont le rendu réel n'apporte rien à un test qui vérifie
 * une carte et un canal temps réel. Son propre comportement est couvert par `MissionSheet.test.tsx`.
 */
jest.mock('@/screens/components/MissionSheet', () => {
  const { View } = require('react-native');
  const ReactLocal = require('react');

  return { MissionSheet: ReactLocal.forwardRef(() => <View />) };
});

jest.mock('@/tracking', () => ({
  useTrackingSession: () => ({
    data: { code: 'TRK-1', status: 'enroute', destination: null, provider: null, eta_minutes: 12, eta_seconds: 720 },
    isLoading: false,
  }),
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
    Button: ({ label }: any) => <Text>{label}</Text>,
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
        navigation={{ navigate: jest.fn() } as never}
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
