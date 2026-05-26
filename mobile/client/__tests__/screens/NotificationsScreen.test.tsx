import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Alice' },
  }),
}));

jest.mock('@/theme', () => ({
  colors: { amber: '#ffb648', slate900: '#0f172a', white: '#fff' },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24 },
  typography: {},
  radius: { md: 14 },
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#fff' }),
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
    Button: ({ label, onPress }: any) => <Text onPress={onPress}>{label}</Text>,
    Icon: () => <View />,
    EmptyState: ({ title }: any) => <Text>{title}</Text>,
  };
});

import { NotificationsScreen } from '../../src/screens/NotificationsScreen';

describe('NotificationsScreen', () => {
  it('renders without crashing', () => {
    expect(render(<NotificationsScreen />).toJSON()).not.toBeNull();
  });

  it('shows notifications title', () => {
    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('Notifications')).toBeTruthy();
  });
});
