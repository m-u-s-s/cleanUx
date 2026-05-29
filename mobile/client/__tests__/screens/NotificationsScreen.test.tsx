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

jest.mock('@/notifications', () => ({
  useNotifications: () => ({ data: [], isLoading: false, refetch: jest.fn().mockResolvedValue(undefined), isRefetching: false }),
  useMarkAllRead: () => ({ mutate: jest.fn() }),
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
    Icon: () => <View />,
    Skeleton: () => <View />,
    EmptyState: ({ title }: any) => <Text>{title}</Text>,
    AnimatedListItem: ({ children }: any) => <View>{children}</View>,
    a11y: { announce: jest.fn(), pressable: (label: string) => ({ accessibilityLabel: label, accessibilityRole: 'button' }) },
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
