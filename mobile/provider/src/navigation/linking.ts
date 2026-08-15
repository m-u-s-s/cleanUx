import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['briopro://', 'https://provider.brio.com'],
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
      /*
       * L'espace société. Les onglets portent leurs propres chemins pour qu'une notification de
       * mission à répartir ouvre la RÉPARTITION, et pas l'accueil de l'espace — un lien profond qui
       * atterrit toujours au même endroit ne vaut pas mieux que pas de lien.
       */
      ProviderCompanySpace: {
        screens: {
          CompanyOverviewTab: 'societe',
          CompanyDispatchTab: 'societe/repartition',
          CompanyFieldTeamsTab: 'societe/equipes',
          CompanyTasksTab: 'societe/taches',
          CompanyChannelsTab: 'societe/canaux',
        },
      },
      CompanyMembers: 'societe/membres',
      CompanySites: 'societe/sites',
      Login: 'login',
      ForgotPassword: 'forgot-password',
      MissionDetail: 'mission/:missionId',
      MissionInbox: 'inbox',
      ProviderChatList: 'chat',
      ProviderChat: 'chat/:threadId',
      ProviderNotifications: 'notifications',
      ProviderNotificationDetail: 'notifications/:id',
      Badges: 'badges',
      Availability: 'availability',
      StripeOnboarding: 'stripe-onboarding',
      KYC: 'kyc',
      ProviderDisputes: 'disputes',
      ProviderRatings: 'ratings',
      Legal: 'legal/:type',
      NotificationPreferences: 'settings/notifications',
      Language: 'settings/language',
      Appearance: 'settings/appearance',
    },
  },
};
