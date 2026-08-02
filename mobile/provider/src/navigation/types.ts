import type { NavigatorScreenParams } from '@react-navigation/native';

export type RootStackParamList = {
  Login: undefined;
  // MainTabs nests TabParamList — declaring it `undefined` hid the fact that reaching a
  // tab (e.g. Earnings) requires `{ screen: '<Tab>' }`.
  MainTabs: NavigatorScreenParams<TabParamList> | undefined;
  // Parcours de vérification : seul écran atteignable tant que le dossier est incomplet.
  ProviderOnboarding: undefined;
  MissionDetail: { missionId: number };
  /** Les courses immédiates proposées à ce prestataire. */
  AsapOffers: undefined;
  MissionInbox: undefined;
  MissionField: { missionId: number };
  MissionTracking: { missionId: number; bookingId: number };
  // Le même écran sert aux deux bouts de la visite : arrivée puis clôture. Ils diffèrent par
  // ce qu'ils valident — une session de suivi d'un côté, une mission de l'autre.
  PresenceScan:
    | { purpose?: 'presence'; sessionId: number }
    | { purpose: 'completion'; missionId: number };
  StripeOnboarding: undefined;
  KYC: undefined;
  Availability: undefined;
  Badges: undefined;
  ProviderDisputes: undefined;
  ProviderRatings: undefined;
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
