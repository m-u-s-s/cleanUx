import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

/*
 * LE CATALOGUE EST BOUCHONNE, PAS LE CHOIX DES CASES.
 *
 * `modulesChoisis` est la vraie fonction : ce qui est simule ici, c'est la reponse du serveur.
 * Bouchonner aussi le choix ferait passer ce test meme si l'ecran cherchait les mauvaises cles.
 */
const mockPlein = {
  context: 'client',
  groups: [
    {
      category: 'croissance',
      label: 'Croissance',
      modules: [
        { key: 'client:peer.owner.vehicles', label: 'Mes vehicules en location', icon: '🅿️', path: '/mes-vehicules' },
        { key: 'client:peer.owner.stays', label: 'Mes logements en location', icon: '🏠', path: '/mes-logements' },
      ],
    },
  ],
};

const mockVide = { context: 'client', groups: [] as unknown[] };

/* Reaffectable : le temoin bascule le catalogue sans recharger les modules — `isolateModules`
   donnerait un second React, et les hooks tombent avant meme le rendu. */
let mockReponse: unknown = null;

jest.mock('@/modules', () => ({
  ...jest.requireActual('@/modules'),
  useModuleCatalogue: () => ({ data: mockReponse }),
}));

const mockNavigate = jest.fn();

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Alice', email: 'alice@test.com', role: 'client' },
    logout: jest.fn(),
    isAuthenticated: true,
    isLoading: false,
  }),
}));

jest.mock('@/booking', () => ({
  useBookings: () => ({
    data: [
      { id: 1, status: 'completed', service_name: 'Nettoyage', scheduled_date: '2026-06-01', address: '1 rue Test', city: 'Bruxelles' },
    ],
    isLoading: false,
  }),
}));

jest.mock('@/theme', () => ({
  ...jest.requireActual('@/theme'),
  // Le thème réel plutôt qu'un objet partiel écrit à la main : il n'a aucun effet de bord,
  // et un stub partiel périme à chaque jeton ajouté — c'est ce qui a cassé ces tests quand
  // les teintes sont apparues.
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    // Le verre du projet : `CarteDeMission` et `GlassSurface` remplacent les aplats de
    // l'accueil, `AnimatedListItem` porte l'entree decalee de la liste.
    CarteDeMission: ({ children }: any) => <View>{children}</View>,
    GlassSurface: ({ children }: any) => <View>{children}</View>,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => <Text onPress={onPress}>{label}</Text>,
    KPICard: ({ title, value }: any) => <View><Text>{title}</Text><Text>{String(value)}</Text></View>,
    Avatar: () => <View />,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Skeleton: () => <View />,
    Icon: () => <View />,
    // La feuille d'actions emploie ces deux-la : sans eux dans le faux, l'ecran leve avant meme
    // d'etre rendu.
    Divider: () => <View />,
    BottomSheet: ({ children }: any) => <View>{children}</View>,
  };
});

/*
 * LA SESSION DE SUIVI EST BOUCHONNÉE ICI. L'accueil ne se fie plus au STATUT de la réservation
 * pour savoir si une mission est vivante : `in_progress` n'arrive qu'au démarrage de
 * l'intervention, et rien ne fait passer la réservation en `en_route` pendant le trajet.
 */
// Le préfixe `mock` est obligatoire : Babel hisse les `jest.mock()` au-dessus des
// déclarations, et seules les variables ainsi nommées ont le droit d'être référencées dans
// la fabrique.
const mockSessionsVivantes = new Set<number>();

jest.mock('@/tracking', () => ({
  useLiveBookingIds: () => mockSessionsVivantes,
}));

import { HomeScreen } from '../../src/screens/HomeScreen';

describe('Mise en location depuis l’accueil', () => {
  beforeEach(() => {
    mockNavigate.mockClear();
    mockReponse = mockPlein;
  });

  it('affiche les deux cases quand le compte a les deux modules', () => {
    const { getByTestId, getByText } = render(<HomeScreen />);

    expect(getByTestId('home-mise-en-location')).toBeTruthy();
    expect(getByText('Mettre ma voiture en location')).toBeTruthy();
    expect(getByText('Mettre mon logement en location')).toBeTruthy();
  });

  it('chaque case ouvre le module par SON chemin, celui du serveur', () => {
    const { getByTestId } = render(<HomeScreen />);

    fireEvent.press(getByTestId('home-louer-vehicule'));
    expect(mockNavigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/mes-vehicules',
      title: 'Mes vehicules en location',
    });

    fireEvent.press(getByTestId('home-louer-logement'));
    expect(mockNavigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/mes-logements',
      title: 'Mes logements en location',
    });
  });

  /*
   * TEMOIN — et c'est la moitie qui compte. Les quatre autres suites de l'accueil rendent un
   * catalogue VIDE et n'attendent aucune case : sans ce controle, elles seraient vertes meme si
   * les cases n'existaient plus du tout.
   */
  it('temoin : un compte sans ces modules ne voit aucune case', () => {
    mockReponse = mockVide;

    const { queryByTestId } = render(<HomeScreen />);

    expect(queryByTestId('home-mise-en-location')).toBeNull();
  });
});
