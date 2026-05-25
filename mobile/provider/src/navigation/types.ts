export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
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
