import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['cleanux-provider://', 'https://provider.cleanux.com'],
  config: {
    screens: {
      MainTabs: {
        screens: {
          Dashboard: '',
          Missions: 'missions',
          Earnings: 'earnings',
          Profile: 'profile',
        },
      },
      Login: 'login',
    },
  },
};
