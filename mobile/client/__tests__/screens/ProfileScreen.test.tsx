import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Alice Martin', email: 'alice@test.com', phone: '+32470000000' },
    logout: jest.fn(),
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
    Button: ({ label, onPress }: any) => <Text onPress={onPress}>{label}</Text>,
    Avatar: ({ name }: any) => <Text>{name}</Text>,
    Icon: () => <View />,
    Divider: () => <View />,
    ListItem: ({ title, onPress }: any) => <Text onPress={onPress}>{title}</Text>,
  };
});

import { ProfileScreen } from '../../src/screens/ProfileScreen';

describe('ProfileScreen', () => {
  it('renders without crashing', () => {
    expect(render(<ProfileScreen />).toJSON()).not.toBeNull();
  });

  /*
   * Le titre était « Profile », en anglais, dans une application entièrement française — relevé à
   * l'écran. Le test épinglait le mot anglais : il gardait donc le défaut en place.
   */
  it('affiche le titre en français', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Mon profil')).toBeTruthy();
  });

  it('shows logout button', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Se déconnecter')).toBeTruthy();
  });

  it('shows GDPR option', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Mes données (RGPD)')).toBeTruthy();
  });
});
