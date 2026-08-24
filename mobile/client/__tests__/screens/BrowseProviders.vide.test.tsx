/**
 * N'AVOIR RIEN CHERCHÉ N'EST PAS N'AVOIR RIEN TROUVÉ.
 *
 * Relevé dans l'émulateur : on ouvre l'onglet « Explorer » et on lit, sans avoir rien saisi,
 * « Aucun prestataire trouvé — Essayez avec d'autres critères de recherche ». De quoi conclure que
 * la plateforme n'a aucun prestataire.
 *
 * La requête ne part pourtant QUE si un métier ou un code postal est renseigné (`enabled` du hook),
 * et c'est délibéré : sans filtre, elle ramènerait l'annuaire entier. C'est donc la liste qui
 * mentait — elle rendait son état vide sur une recherche qui n'avait jamais eu lieu.
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react-native';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('react-native-reanimated', () => {
  const { View } = require('react-native');

  return {
    __esModule: true,
    default: { View: ({ children }: any) => <View>{children}</View> },
    FadeIn: { duration: () => ({}) },
  };
});

/** Le hook reste inerte tant qu'aucun filtre n'est saisi : c'est exactement ce que l'écran doit lire. */
const mockResultat = { data: undefined as any, isLoading: false, refetch: jest.fn(), isRefetching: false };

jest.mock('@/booking', () => ({
  useBrowseProviders: () => mockResultat,
}));

jest.mock('@/ui', () => {
  const { View, Text, TextInput: RNTextInput } = require('react-native');

  return {
    // Crochet d'accessibilite, pas un composant : l'ecran ne s'anime pas sous test.
    useEntree: () => undefined,
    useDuree: () => 0,
    Screen: ({ children }: any) => <View>{children}</View>,
    TextInput: ({ label, value, onChangeText, placeholder }: any) => (
      <RNTextInput
        accessibilityLabel={label}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
      />
    ),
    Badge: ({ label }: any) => <Text>{label}</Text>,
    Avatar: () => <View />,
    Skeleton: () => <View />,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    EmptyState: ({ title, message }: any) => (
      <View>
        <Text>{title}</Text>
        <Text>{message}</Text>
      </View>
    ),
  };
});

jest.mock('@/theme', () => ({
  colors: { brand: { 500: '#6366f1' }, surface: { 900: '#0f172a' } },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '2xl': 24 },
    fontWeight: { medium: '500', semibold: '600', bold: '700' },
  },
  radius: { md: 14, pill: 999 },
  shadows: { soft: {}, xs: {} },
  useThemeColors: jest.requireActual('@/theme/useThemeColors').useThemeColors,
}));

import { BrowseProvidersScreen } from '@/screens/BrowseProvidersScreen';

describe('Explorer les prestataires — état vide', () => {
  beforeEach(() => {
    mockResultat.data = undefined;
  });

  it('invite à chercher à l’ouverture, au lieu d’annoncer un échec', () => {
    render(<BrowseProvidersScreen />);

    expect(screen.getByText('Trouvez un prestataire')).toBeTruthy();
    expect(screen.queryByText('Aucun prestataire trouvé')).toBeNull();
  });

  it('annonce bien l’absence de résultat une fois la recherche lancée', () => {
    /*
     * TÉMOIN POSITIF. Sans lui, le test ci-dessus passerait au vert même si l'écran avait
     * simplement PERDU son message d'absence de résultat — on aurait remplacé un message faux par
     * un message manquant, et la suite ne l'aurait pas vu.
     */
    render(<BrowseProvidersScreen />);

    fireEvent.changeText(screen.getByLabelText('Code postal'), '1000');

    expect(screen.getByText('Aucun prestataire trouvé')).toBeTruthy();
    expect(screen.queryByText('Trouvez un prestataire')).toBeNull();
  });

  it('propose un code postal du marché principal en exemple', () => {
    render(<BrowseProvidersScreen />);

    expect(screen.getByLabelText('Code postal').props.placeholder).toBe('1000');
  });
});
