export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  /**
   * `mode` prépositionne le créneau : `asap` pour une intervention immédiate, `scheduled` pour
   * un rendez-vous. Optionnel — sans lui, le parcours se comporte comme avant.
   */
  BookingWizard: { mode?: 'asap' | 'scheduled' } | undefined;
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
  Legal: { type: 'terms' | 'privacy' };
  // Polish — UX screens
  NotificationPreferences: undefined;
  Language: undefined;
  Appearance: undefined;
  Invoices: undefined;
  InvoiceDetail: { id: number };
  // Embedded web modules
  EmbeddedModule: { path: string; title: string };
};

export type BookingStackParamList = {
  BookingStep1: undefined;
  BookingStep2: undefined;
  BookingStep3: undefined;
  BookingStep4: undefined;
  // SP2 — provider selection (type + favourites + premium pick)
  BookingStepProvider: undefined;
  // SP2 palier 3 — premium provider search (selection mode returns the pick to the wizard)
  BookingProviderSearch: undefined;
  // SP3 Task 9 — premium provider COMPANY search (selection mode returns the org id).
  // The wizard passes its postal code; serviceZoneId stays for back-compat.
  BookingCompanySearch:
    | { postalCode?: string; serviceZoneId?: number; serviceCatalogId?: number }
    | undefined;
  BookingStep5: undefined;
};

export type TabParamList = {
  Home: undefined;
  Explore: undefined;
  Bookings: undefined;
  Profile: undefined;
};
