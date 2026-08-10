/**
 * TOUTES LES MISSIONS EN COURS SUR L'ACCUEIL — et joignables.
 *
 * L'accueil affichait UNE carte focale, puis une ligne « 2 autres réservations en cours » : un
 * chiffre sur lequel on ne peut pas appuyer. Un client ayant deux interventions le même jour ne
 * pouvait atteindre la seconde que par un autre onglet, et rien ne le lui disait.
 *
 * Et la destination du tap dépendait du STATUT de la réservation, qui ne passe `in_progress` qu'au
 * démarrage de l'intervention : pendant le trajet du prestataire, appuyer ouvrait le détail au lieu
 * du suivi en direct. C'est la session de suivi qui sait qu'il se passe quelque chose.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
}));

jest.mock('@/auth', () => ({ useAuth: () => ({ user: { name: 'Marie', email: 'm@x.be' } }) }));

const reservation = (id: number, state: string, nom: string) => ({
  id,
  status: state,
  state,
  service_name: nom,
  scheduled_date: '2026-06-01',
  scheduled_time: '10:00',
  address: '1 rue Test',
  city: 'Bruxelles',
  postal_code: '1000',
  created_at: '',
});

jest.mock('@/booking', () => ({
  useBookings: () => ({
    data: [
      reservation(10, 'confirmed', 'Nettoyage'),
      reservation(11, 'confirmed', 'Vitrerie'),
      reservation(12, 'pending', 'Peinture'),
    ],
    isLoading: false,
  }),
}));

const mockSessionsVivantes = new Set<number>();

jest.mock('@/tracking', () => ({
  useLiveBookingIds: () => mockSessionsVivantes,
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
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
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
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, '2xl': 24 },
    fontWeight: { semibold: '600', bold: '700' },
  },
  radius: { md: 14, pill: 999 },
  shadows: { soft: {}, xs: {} },
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

import { HomeScreen } from '@/screens/HomeScreen';

describe("Missions en cours sur l'accueil", () => {
  beforeEach(() => {
    mockNavigate.mockClear();
    mockSessionsVivantes.clear();
  });

  it('liste les autres missions, pas seulement leur nombre', () => {
    render(<HomeScreen />);

    // La première est la carte focale ; les deux autres doivent exister comme éléments à part
    // entière, chacun avec sa propre destination.
    expect(screen.getByTestId('home-other-booking-11')).toBeTruthy();
    expect(screen.getByTestId('home-other-booking-12')).toBeTruthy();
  });

  it('ouvrir une autre mission mène à son détail', () => {
    render(<HomeScreen />);

    fireEvent.press(screen.getByTestId('home-other-booking-11'));

    expect(mockNavigate).toHaveBeenCalledWith('BookingDetail', { bookingId: 11 });
  });

  it('une mission vivante de la liste mène au suivi en direct', () => {
    // Deux prestataires en route en même temps : le premier prend la carte focale, le second
    // reste dans la liste — et doit lui aussi ouvrir le suivi, pas le détail.
    mockSessionsVivantes.add(11);
    mockSessionsVivantes.add(12);

    render(<HomeScreen />);

    fireEvent.press(screen.getByTestId('home-other-booking-12'));

    // Sans cela, le tap ouvrait le détail — sans carte, sans code de présence — tant que
    // l'intervention n'avait pas démarré.
    expect(mockNavigate).toHaveBeenCalledWith('MissionTracking', { bookingId: 12 });
  });

  it('la mission vivante devient la carte focale', () => {
    mockSessionsVivantes.add(12);

    render(<HomeScreen />);

    fireEvent.press(screen.getByTestId('home-focus-booking'));

    // Celle qui se passe MAINTENANT prend la place principale, même si une autre vient avant
    // elle dans la liste.
    expect(mockNavigate).toHaveBeenCalledWith('MissionTracking', { bookingId: 12 });
  });
});
