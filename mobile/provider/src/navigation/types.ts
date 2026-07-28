import type { NavigatorScreenParams } from '@react-navigation/native';

export type RootStackParamList = {
  Login: undefined;
  // MainTabs nests TabParamList — declaring it `undefined` hid the fact that reaching a
  // tab (e.g. Earnings) requires `{ screen: '<Tab>' }`.
  MainTabs: NavigatorScreenParams<TabParamList> | undefined;
  // Parcours de vérification : seul écran atteignable tant que le dossier est incomplet.
  ProviderOnboarding: undefined;
  MissionDetail: { missionId: number };
  MissionInbox: undefined;
  MissionField: { missionId: number };
  StripeOnboarding: undefined;
  KYC: undefined;
  Availability: undefined;
  Badges: undefined;
  ProviderDisputes: undefined;
  ProviderRatings: undefined;
  Onboarding: undefined;
  ProviderChatList: undefined;
  ProviderChat: { threadId: number; title: string };
  ProviderNotifications: undefined;
  ForgotPassword: undefined;
  Legal: { type: 'terms' | 'privacy' };
  // Polish — UX screens
  NotificationPreferences: undefined;
  Language: undefined;
  Appearance: undefined;
};

export type TabParamList = {
  Dashboard: undefined;
  Missions: undefined;
  Earnings: undefined;
  Profile: undefined;
};
