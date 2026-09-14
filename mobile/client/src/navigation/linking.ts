import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['brio://', 'https://app.brio.com'],
  config: {
    screens: {
      /*
        LES CINQ ONGLETS, ET SEULEMENT EUX. `Explore` a cédé sa place à `Location` quand la barre
        est passée à cinq portes : l'entrée était restée ici, donc `brio://explore` retombait sur
        l'accueil, et `Location` comme `Messages` n'étaient joignables par aucun lien.
      */
      MainTabs: {
        screens: {
          Home: '',
          Location: 'location',
          Bookings: 'bookings',
          Messages: 'messages',
          Profile: 'profile',
        },
      },
      // `brio://explore` existe dans la nature : l'ecran a quitte la barre, pas l'application.
      Explore: 'explore',
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
