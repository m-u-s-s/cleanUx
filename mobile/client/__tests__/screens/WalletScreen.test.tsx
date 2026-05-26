import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
  useRoute: () => ({ params: {} }),
}));

jest.mock('@/auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Alice', email: 'alice@test.com' },
  }),
}));

jest.mock('@/theme', () => ({
  colors: { amber: '#ffb648', slate900: '#0f172a', white: '#fff', emerald500: '#10b981' },
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
    Icon: () => <View />,
    KPICard: ({ label, value }: any) => <View><Text>{label}</Text><Text>{value}</Text></View>,
  };
});

import { WalletScreen } from '../../src/screens/WalletScreen';

describe('WalletScreen', () => {
  it('renders without crashing', () => {
    expect(render(<WalletScreen />).toJSON()).not.toBeNull();
  });

  it('shows wallet title', () => {
    const { getByText } = render(<WalletScreen />);
    expect(getByText(/Portefeuille|Wallet/i)).toBeTruthy();
  });
});
