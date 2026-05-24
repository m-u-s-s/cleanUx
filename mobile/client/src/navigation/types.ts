export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  BookingWizard: undefined;
  MissionTracking: { bookingId: number };
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
