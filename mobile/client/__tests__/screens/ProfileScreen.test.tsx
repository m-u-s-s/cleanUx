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
  colors: { amber: '#ffb648', slate900: '#0f172a', white: '#fff', slate500: '#64748b' },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: {},
  radius: { md: 14 },
  shadows: {},
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#fff' }),
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => <Text onPress={onPress}>{label}</Text>,
    Avatar: ({ name }: any) => <Text>{name}</Text>,
    Icon: () => <View />,
    ListItem: ({ title, onPress }: any) => <Text onPress={onPress}>{title}</Text>,
  };
});

import { ProfileScreen } from '../../src/screens/ProfileScreen';

describe('ProfileScreen', () => {
  it('renders without crashing', () => {
    expect(render(<ProfileScreen />).toJSON()).not.toBeNull();
  });

  it('shows user name', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Alice Martin')).toBeTruthy();
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
