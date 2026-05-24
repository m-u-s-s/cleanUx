import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['cleanux://', 'https://app.cleanux.com'],
  config: {
    screens: {
      MainTabs: { screens: { Home: '', Bookings: 'bookings', Notifications: 'notifications', Profile: 'profile' } },
      Login: 'login',
    },
  },
};
