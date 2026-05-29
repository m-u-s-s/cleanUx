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
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#fff', textMuted: '#64748b', textSecondary: '#94a3b8' }),
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

  it('shows profile title', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Profile')).toBeTruthy();
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
