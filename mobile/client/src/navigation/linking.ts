import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['brio://', 'https://app.brio.com'],
  config: {
    screens: {
      MainTabs: {
        screens: {
          Home: '',
          Explore: 'explore',
          Bookings: 'bookings',
          Profile: 'profile',
        },
      },
      Login: 'login',
      ForgotPassword: 'forgot-password',
      BookingDetail: 'booking/:bookingId',
      Chat: 'chat/:threadId',
      ChatList: 'chat',
      Loyalty: 'loyalty',
      Referral: 'referral',
      ProfileEdit: 'profile/edit',
      Notifications: 'notifications',
      NotificationDetail: 'notifications/:id',
      Tips: 'booking/:bookingId/tips',
      Rating: 'booking/:bookingId/rate',
      Disputes: 'disputes',
      GDPR: 'gdpr',
      NPS: 'nps',
      Legal: 'legal/:type',
      NotificationPreferences: 'settings/notifications',
      Language: 'settings/language',
      Appearance: 'settings/appearance',
    },
  },
};
