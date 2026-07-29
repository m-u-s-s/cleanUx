import React from 'react';
import { render, screen, waitFor, act, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { notifyManager } from '@tanstack/query-core';
import MockAdapter from 'axios-mock-adapter';

// React Query planifie ses notifications via un `setTimeout(0)` par défaut : cette macrotâche
// peut se déclencher après qu'un premier `waitFor` a déjà résolu (dès le premier rendu), donc
// hors de toute portée `act()`, et React logue « not wrapped in act ». On force ici une
// notification synchrone, dans ce fichier de test seulement, pour que la mise à jour de
// l'inbox de missions reste attribuable au rendu ou au `waitFor` qui l'attend.
notifyManager.setScheduler((callback) => callback());

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

// `mockNavigate` doit être préfixé `mock` : les factories `jest.mock` sont hissées
// (hoisting babel-jest) avant les déclarations `const` du fichier, donc toute variable
// capturée par la factory doit porter ce préfixe pour survivre au hoisting.
const mockNavigate = jest.fn();
const mockIsFocused = { current: true };
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useIsFocused: () => mockIsFocused.current,
}));

const mockPermission = { current: 'granted' as 'pending' | 'granted' | 'denied' };
// Dernière valeur du drapeau `enabled` passé à useGpsWatcher : c'est lui qui décide si un
// watcher GPS haute précision tourne pendant que le prestataire est sur un autre onglet.
const mockGpsEnabled = { current: null as boolean | null };
// Capture le callback `onPosition` passé par ProviderMap à useGpsWatcher, pour qu'un test
// puisse simuler l'arrivée d'une position GPS (`mockOnPosition.current?.({ ... })`) sans
// dépendre du vrai module expo-location.
const mockOnPosition = { current: null as ((pos: { latitude: number; longitude: number; speed: number | null; heading: number | null }) => void) | null };
jest.mock('@/tracking', () => ({
  useGpsWatcher: (enabled: boolean, onPosition: (pos: any) => void) => {
    mockGpsEnabled.current = enabled;
    mockOnPosition.current = onPosition;
    return { permission: mockPermission.current };
  },
  distanceKmTo: jest.requireActual('../../src/tracking/distance').distanceKmTo,
  formatDistance: jest.requireActual('../../src/tracking/distance').formatDistance,
}));

// `react-native-maps` est déjà redirigé vers __mocks__/react-native-maps par moduleNameMapper :
// il suffit donc de faire renvoyer ce module (ou null) par loadMapModule.
const mockMapModule = { current: true };
const mockMapRenderable = { current: true };

jest.mock('@/maps', () => ({
  loadMapModule: () => {
    if (!mockMapModule.current) return null;
    const maps = require('react-native-maps');
    return { MapView: maps.default, Marker: maps.Marker, Callout: maps.Callout };
  },
  // Sur Android, la carte n'est affichable qu'avec une clé Google Maps dans le manifeste natif ;
  // sans elle le rendu LÈVE au lieu de dégrader. `mockMapRenderable` pilote ce second verrou,
  // distinct de la présence du module lui-même.
  isMapRenderable: () => mockMapRenderable.current,
}));

import { apiClient } from '@/api';
import { ProviderMap } from '@/screens/components/ProviderMap';

// `require`, pas `import` : les types publiés de react-native-maps n'ont pas
// `mockAnimateToRegion` (il n'existe que dans notre stub Jest, __mocks__/react-native-maps,
// vers lequel `moduleNameMapper` redirige déjà 'react-native-maps' au runtime — voir plus haut).
const { mockAnimateToRegion } = require('react-native-maps');

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockMapModule.current = true;
  mockMapRenderable.current = true;
  mockPermission.current = 'granted';
  mockIsFocused.current = true;
  mockGpsEnabled.current = null;
  mockOnPosition.current = null;
  mockAnimateToRegion.mockClear();
  mockNavigate.mockClear();
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
});

const MOCK_ASSIGNMENT = {
  id: 1,
  mission_id: 10,
  assignment_status: 'assigned',
  booking_id: 100,
  service_name: 'Nettoyage',
  client_name: 'Jean Martin',
  address: '5 Rue du Bois',
  city: 'Liege',
  scheduled_date: '2026-06-10',
  scheduled_time: '09:00',
  // Coordonnées de la mission : c'est ce que le repli de centrage (Finding 1) doit utiliser
  // quand aucune position GPS n'est encore connue.
  latitude: 50.6326,
  longitude: 5.5797,
  created_at: '2026-06-09T09:00:00Z',
};

describe('ProviderMap', () => {
  it('rend la carte quand le module natif est disponible', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
  });

  // DashboardScreen est un onglet : une fois visité, il reste monté pour toute la session. Un
  // watcher GPS Accuracy.High / 5 s / 10 m câblé en dur continuerait donc de tourner pendant que
  // le prestataire est sur Missions, Revenus ou Profil — consommation nette nouvelle, puisque
  // l'ancien tableau de bord n'utilisait aucun GPS.
  it('surveille le GPS quand la carte est au premier plan', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
    expect(mockGpsEnabled.current).toBe(true);
  });

  it('coupe la surveillance GPS quand l écran n est plus au premier plan', async () => {
    mockIsFocused.current = false;

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
    expect(mockGpsEnabled.current).toBe(false);
  });

  it('rend le placeholder texte quand le module natif est absent', async () => {
    mockMapModule.current = false;

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-fallback')).toBeTruthy());
    expect(screen.queryByTestId('provider-map')).toBeNull();
  });

  /**
   * Le module peut être présent SANS que la carte soit affichable : sur Android, Google Maps
   * exige une clé dans le manifeste natif et, faute de clé, LÈVE au lieu de dégrader —
   * « IllegalStateException: API key not found » — emportant le tableau de bord entier, dont la
   * carte est l'élément principal. Le repli doit donc couvrir ce cas comme l'absence de module.
   */
  it('rend le placeholder quand la carte est indisponible faute de clé', async () => {
    mockMapModule.current = true;
    mockMapRenderable.current = false;

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-fallback')).toBeTruthy());
    expect(screen.queryByTestId('provider-map')).toBeNull();
  });

  it('explique une permission GPS refusée', async () => {
    mockPermission.current = 'denied';

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-permission-notice')).toBeTruthy());
  });

  it('annonce l absence de mission en attente', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText(/Aucune mission en attente/)).toBeTruthy());
  });

  it('affiche une erreur récupérable quand l inbox échoue', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(500);

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText(/Réessayer/)).toBeTruthy());
    expect(screen.getByTestId('provider-map')).toBeTruthy();
  });

  it('ne recentre qu une seule fois même si plusieurs positions GPS arrivent', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());

    act(() => {
      mockOnPosition.current?.({ latitude: 50.85, longitude: 4.35, speed: null, heading: null });
    });
    await waitFor(() => expect(mockAnimateToRegion).toHaveBeenCalledTimes(1));

    act(() => {
      mockOnPosition.current?.({ latitude: 50.9, longitude: 4.4, speed: null, heading: null });
    });
    // Laisse le temps à un éventuel second appel de se produire avant d'affirmer qu'il n'y en a pas :
    // c'est le garde-fou contre une régression qui referait sauter la carte à chaque tick GPS.
    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
    expect(mockAnimateToRegion).toHaveBeenCalledTimes(1);
  });

  it('centre sur la première mission géolocalisée si aucune position GPS n est encore connue', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(mockAnimateToRegion).toHaveBeenCalledTimes(1));
    expect(mockAnimateToRegion).toHaveBeenCalledWith(
      expect.objectContaining({ latitude: MOCK_ASSIGNMENT.latitude, longitude: MOCK_ASSIGNMENT.longitude }),
      expect.any(Number),
    );
  });

  it('recentre une seconde fois quand une position GPS arrive après un centrage sur une mission', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(mockAnimateToRegion).toHaveBeenCalledTimes(1));

    act(() => {
      mockOnPosition.current?.({ latitude: 50.85, longitude: 4.35, speed: null, heading: null });
    });

    await waitFor(() => expect(mockAnimateToRegion).toHaveBeenCalledTimes(2));
    expect(mockAnimateToRegion).toHaveBeenLastCalledWith(
      expect.objectContaining({ latitude: 50.85, longitude: 4.35 }),
      expect.any(Number),
    );
  });

  const GEOLOCATED = {
    id: 2, mission_id: 20, assignment_status: 'assigned', expires_at: null, remaining_seconds: null,
    booking_id: 200, service_name: 'Peinture', client_name: 'Paul Klee', address: '10 Rue des Arts',
    city: 'Gent', postal_code: '9000', scheduled_date: '2026-06-15', scheduled_time: '14:00',
    latitude: 51.0543, longitude: 3.7174, created_at: '2026-06-14T09:00:00Z',
  };
  const UNLOCATED = { ...GEOLOCATED, id: 3, booking_id: 201, latitude: null, longitude: null };

  it('trace un marqueur par mission géolocalisée', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED, UNLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    // Marqueurs identifiés par l'id de l'AFFECTATION (a.id), pas par booking_id : ce dernier est
    // nullable côté API (une mission dont la réservation n'est pas résolue le renvoie à null).
    await waitFor(() => expect(screen.getByTestId(`mission-marker-${GEOLOCATED.id}`)).toBeTruthy());
    expect(screen.queryByTestId(`mission-marker-${UNLOCATED.id}`)).toBeNull();
    expect(screen.getByText('1 mission sans localisation')).toBeTruthy();
  });

  it('trace deux marqueurs distincts pour deux missions sans booking_id résolu', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, {
      data: [
        { ...GEOLOCATED, id: 7, mission_id: 70, booking_id: null },
        { ...GEOLOCATED, id: 8, mission_id: 80, booking_id: null, latitude: 50.63, longitude: 5.57 },
      ],
    });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    // Avec key={a.booking_id}, ces deux marqueurs partageaient la clé `null` : React n'en gardait
    // qu'un et l'autre disparaissait de la carte.
    await waitFor(() => expect(screen.getByTestId('mission-marker-7')).toBeTruthy());
    expect(screen.getByTestId('mission-marker-8')).toBeTruthy();
  });

  it('affiche le service et le client dans le callout', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Peinture')).toBeTruthy());
    expect(screen.getByText('Paul Klee')).toBeTruthy();
  });

  it('navigue vers le détail avec l identifiant de MISSION, pas celui de la réservation', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByTestId('map-callout'));
    fireEvent.press(screen.getByTestId('map-callout'));

    // MissionDetailScreen appelle GET /provider/missions/{missionId}, lié au modèle Mission :
    // passer booking_id (200 dans cette fixture) ouvre une mission sans rapport, ou 404.
    expect(mockNavigate).toHaveBeenCalledWith('MissionDetail', { missionId: GEOLOCATED.mission_id });
    expect(mockNavigate).not.toHaveBeenCalledWith('MissionDetail', { missionId: GEOLOCATED.booking_id });
  });

  it('affiche la distance dans le callout quand une position GPS est connue', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId(`mission-marker-${GEOLOCATED.id}`)).toBeTruthy());

    // Bruxelles → Gand : ~49,8 km, assez loin pour que la valeur formatée soit sans
    // ambiguïté (pas un arrondi qui coïnciderait par hasard avec autre chose à l'écran).
    act(() => {
      mockOnPosition.current?.({ latitude: 50.85, longitude: 4.35, speed: null, heading: null });
    });

    await waitFor(() => expect(screen.getByText('49.8 km')).toBeTruthy());
  });
});
