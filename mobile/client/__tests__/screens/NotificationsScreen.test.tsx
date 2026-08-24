import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

const mockNavigate = jest.fn();

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: mockNavigate }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Alice' },
  }),
}));

/*
 * LA LISTE EST MONTÉE AVEC UNE VRAIE NOTIFICATION, PAS AVEC UN TABLEAU VIDE.
 *
 * Ce fichier ne montait que `data: []` : il ne rendait donc JAMAIS une ligne, et n'a pas pu voir
 * que l'écran affichait `item.title` / `item.body` — deux champs que l'API n'a jamais envoyés.
 * Toutes les lignes sortaient blanches et les deux tests restaient verts.
 *
 * `severityVariant` et `formatNotificationDate` viennent du vrai module : ce sont des fonctions
 * pures, et un stub écrit à la main périmerait au premier champ ajouté.
 */
const mockNotif = {
  id: 'n-1',
  type: 'RappelRdv',
  type_key: 'rendezvous',
  label: 'Rendez-vous',
  title: 'Rappel de mission',
  body: 'Mission demain 09:00 chez Dupont',
  severity: 'info' as const,
  context: { rdv_id: 4242, zone: 'Bruxelles 1000' },
  action_url: 'http://brio.test/dashboard/client',
  action_path: '/dashboard/client',
  action_label: 'Aller au tableau de bord',
  read_at: null,
  created_at: '2026-08-15T09:00:00Z',
};

jest.mock('@/notifications', () => ({
  ...jest.requireActual('@/notifications/presentation'),
  useNotifications: () => ({
    data: [mockNotif],
    isLoading: false,
    refetch: jest.fn().mockResolvedValue(undefined),
    isRefetching: false,
  }),
  useMarkAllRead: () => ({ mutate: jest.fn() }),
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
    // Crochet d'accessibilite, pas un composant : l'ecran ne s'anime pas sous test.
    useEntree: () => undefined,
    useDuree: () => 0,
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => <Text onPress={onPress}>{label}</Text>,
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Icon: () => <View />,
    Skeleton: () => <View />,
    EmptyState: ({ title }: any) => <Text>{title}</Text>,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    a11y: { announce: jest.fn(), pressable: (label: string) => ({ accessibilityLabel: label, accessibilityRole: 'button' }) },
  };
});

import { NotificationsScreen } from '../../src/screens/NotificationsScreen';

describe('NotificationsScreen', () => {
  beforeEach(() => mockNavigate.mockClear());

  it('renders without crashing', () => {
    expect(render(<NotificationsScreen />).toJSON()).not.toBeNull();
  });

  it('shows notifications title', () => {
    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('Notifications')).toBeTruthy();
  });

  it('affiche le contenu de la notification, pas une ligne vide', () => {
    const { getByText } = render(<NotificationsScreen />);

    expect(getByText('Rappel de mission')).toBeTruthy();
    expect(getByText('Mission demain 09:00 chez Dupont')).toBeTruthy();
    expect(getByText('Rendez-vous')).toBeTruthy();
    expect(getByText('Nouveau')).toBeTruthy();
  });

  it('montre le contexte que la recherche indexe déjà', () => {
    const { getByText } = render(<NotificationsScreen />);

    expect(getByText(/4242/)).toBeTruthy();
    expect(getByText(/Bruxelles 1000/)).toBeTruthy();
  });

  /*
   * DEMANDE EXPLICITE : des cartes, et de l'air entre elles.
   *
   * La liste empilait des lignes séparées par un filet d'un pixel — « trop collées ». Ces deux
   * assertions sont cosmétiques et c'est assumé : sans elles, un aplatissement du style repasserait
   * sans bruit, et personne ne relit une liste pour vérifier qu'elle respire encore.
   */
  it('rend chaque notification dans une carte espacée', () => {
    const { getByLabelText, UNSAFE_getByType } = render(<NotificationsScreen />);

    const carte = getByLabelText(/Rappel de mission/);
    const styleCarte = Object.assign({}, ...[carte.props.style].flat(Infinity).filter(Boolean));

    expect(styleCarte.borderRadius).toBeGreaterThan(0);
    expect(styleCarte.padding).toBeGreaterThan(0);
    // Le liséré de sévérité, à gauche.
    expect(styleCarte.borderLeftWidth).toBeGreaterThan(0);

    const { FlatList } = require('react-native');
    const liste = UNSAFE_getByType(FlatList);
    const styleListe = Object.assign({}, ...[liste.props.contentContainerStyle].flat(Infinity).filter(Boolean));

    expect(styleListe.gap).toBeGreaterThan(0);
  });

  it('ouvre la fiche au lieu de sauter sur la page de résolution', () => {
    const { getByLabelText } = render(<NotificationsScreen />);

    fireEvent.press(getByLabelText(/Rappel de mission/));

    expect(mockNavigate).toHaveBeenCalledWith('NotificationDetail', { id: 'n-1' });
  });
});
