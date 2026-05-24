export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  MissionDetail: { missionId: number };
  MissionInbox: undefined;
  MissionField: { missionId: number };
  QRShow: { missionId: number; action: 'start' | 'end' };
  Checklist: { missionId: number; inspectionId: number };
  StripeOnboarding: undefined;
  KYC: undefined;
  AvailabilityEdit: undefined;
};

export type TabParamList = {
  Dashboard: undefined;
  Missions: undefined;
  Earnings: undefined;
  Profile: undefined;
};
