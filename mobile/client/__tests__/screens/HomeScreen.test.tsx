import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
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
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#fff', textMuted: '#64748b', textSecondary: '#94a3b8' }),
}));

jest.mock('@/ui', () => {
  const { View, Text } = require('react-native');
  return {
    Screen: ({ children }: any) => <View>{children}</View>,
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

import { HomeScreen } from '../../src/screens/HomeScreen';

describe('HomeScreen', () => {
  it('renders without crashing', () => {
    const tree = render(<HomeScreen />);
    expect(tree.toJSON()).not.toBeNull();
  });

  it('shows greeting with user name', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText(/Bonjour/)).toBeTruthy();
  });

  it('shows quick action buttons', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText('Mes réservations')).toBeTruthy();
    expect(getByText('Messagerie')).toBeTruthy();
    expect(getByText('Fidélité')).toBeTruthy();
  });

  it('shows completed bookings KPI', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText('Terminées')).toBeTruthy();
  });
});
