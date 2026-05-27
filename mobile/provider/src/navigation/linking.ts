import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['cleanuxpro://', 'https://provider.cleanux.com'],
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
      ForgotPassword: 'forgot-password',
      MissionDetail: 'mission/:missionId',
      MissionInbox: 'inbox',
      ProviderChatList: 'chat',
      ProviderChat: 'chat/:threadId',
      ProviderNotifications: 'notifications',
      Badges: 'badges',
      Availability: 'availability',
      StripeOnboarding: 'stripe-onboarding',
      KYC: 'kyc',
      Onboarding: 'onboarding',
      ProviderDisputes: 'disputes',
      ProviderRatings: 'ratings',
      Legal: 'legal/:type',
      NotificationPreferences: 'settings/notifications',
      Language: 'settings/language',
      Appearance: 'settings/appearance',
    },
  },
};
