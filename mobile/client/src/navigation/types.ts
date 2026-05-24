export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  BookingWizard: undefined;
  MissionTracking: { bookingId: number };
  BookingDetail: { bookingId: number };
  QRScan: { bookingId: number; action: 'start' | 'end' };
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
