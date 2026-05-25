export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  BookingWizard: undefined;
  MissionTracking: { bookingId: number };
  BookingDetail: { bookingId: number };
  QRScan: { bookingId: number; action: 'start' | 'end' };
  PaymentCheckout: { bookingId: number };
  SavedPaymentMethods: undefined;
  Chat: { threadId: number; title: string };
  ChatList: undefined;
  Notifications: undefined;
  // Sprint 9
  Rating: { bookingId: number };
  Loyalty: undefined;
  Referral: undefined;
  AiQuote: undefined;
  // Sprint 10
  Disputes: undefined;
  GDPR: undefined;
  ProfileEdit: undefined;
  Tips: { bookingId: number };
  NPS: undefined;
  ForgotPassword: undefined;
};

export type BookingStackParamList = {
  BookingStep1: undefined;
  BookingStep2: undefined;
  BookingStep3: undefined;
  BookingStep4: undefined;
  BookingStep5: undefined;
};

export type TabParamList = {
  Home: undefined;
  Explore: undefined;
  Bookings: undefined;
  Profile: undefined;
};
