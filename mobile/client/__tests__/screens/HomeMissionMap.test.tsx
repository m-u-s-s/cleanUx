/**
 * La carte de l'accueil s'affiche pour une mission réellement en cours.
 *
 * Elle ne s'affichait jamais. L'accueil cherchait une réservation au statut `in_progress`, or le
 * domaine emploie un vocabulaire FRANÇAIS — `en_route`, `sur_place` — que l'API renvoyait brut.
 * Aucune réservation ne portait donc jamais le statut attendu, et la condition d'affichage de la
 * carte n'était jamais vraie.
 *
 * Le serveur expose désormais un état normalisé, et c'est lui que l'écran filtre. Ces tests
 * verrouillent les deux bouts : la carte apparaît sur une mission en cours quel que soit le
 * vocabulaire d'origine, et reste absente quand il n'y a rien à situer.
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';

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

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

jest.mock('@/auth', () => ({ useAuth: () => ({ user: { name: 'Jean', email: 'j@x.be' } }) }));

/** Pilote la réservation servie à l'écran depuis chaque test. */
const bookingState: { state?: string; status: string } = { state: 'in_progress', status: 'en_route' };

jest.mock('@/booking', () => ({
  useBookings: () => ({
    data: [{
      id: 10,
      status: bookingState.status,
      state: bookingState.state,
      service_name: 'Nettoyage',
      scheduled_date: '2026-06-01',
      scheduled_time: '10:00',
      address: '1 rue Test',
      city: 'Bruxelles',
      postal_code: '1000',
      created_at: '',
    }],
    isLoading: false,
  }),
}));

jest.mock('@/screens/components/HomeActionsSheet', () => {
  const { View } = require('react-native');
  const ReactLocal = require('react');
  return { HomeActionsSheet: ReactLocal.forwardRef(() => <View />) };
});

jest.mock('@/screens/components/HomeMissionMap', () => {
  const { View } = require('react-native');
  return { HomeMissionMap: ({ bookingId }: any) => <View testID={`mission-map-${bookingId}`} /> };
});

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}><Text>{label}</Text></TouchableOpacity>
    ),
    Avatar: () => <View />,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    Icon: () => <View />,
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5' }, surface: { 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: { fontSize: { xs: 12, sm: 14, base: 16, lg: 18, '2xl': 24 }, fontWeight: { semibold: '600', bold: '700' } },
  radius: { md: 14, pill: 999 },
  shadows: { soft: {}, xs: {} },
  useThemeColors: () => ({ text: '#000', textMuted: '#666', textSecondary: '#444', card: '#fff' }),
}));

import { HomeScreen } from '@/screens/HomeScreen';

describe("Carte de l'accueil client", () => {
  /**
   * Le défaut rapporté : une mission `en_route` — le vocabulaire réel du domaine — n'était pas
   * reconnue comme en cours, donc la carte ne s'affichait jamais.
   */
  it('affiche la carte pour une mission en route', async () => {
    bookingState.status = 'en_route';
    bookingState.state = 'in_progress';

    render(<HomeScreen />);

    await waitFor(() => expect(screen.getByTestId('mission-map-10')).toBeTruthy());
  });

  it('affiche la carte pour une mission sur place', async () => {
    bookingState.status = 'sur_place';
    bookingState.state = 'in_progress';

    render(<HomeScreen />);

    await waitFor(() => expect(screen.getByTestId('mission-map-10')).toBeTruthy());
  });

  /** Rien à situer tant que la mission n'a pas démarré : la carte reste absente. */
  it("n'affiche pas de carte pour une réservation confirmée", async () => {
    bookingState.status = 'confirme';
    bookingState.state = 'confirmed';

    render(<HomeScreen />);

    await waitFor(() => expect(screen.getByTestId('home-focus-booking')).toBeTruthy());
    expect(screen.queryByTestId('mission-map-10')).toBeNull();
  });

  /**
   * Repli sur le statut brut quand le serveur ne renvoie pas encore d'état normalisé — une
   * application à jour peut interroger un serveur qui ne l'est pas.
   */
  it('reconnaît un statut anglais sans état normalisé', async () => {
    bookingState.status = 'in_progress';
    bookingState.state = undefined;

    render(<HomeScreen />);

    await waitFor(() => expect(screen.getByTestId('mission-map-10')).toBeTruthy());
  });
});
