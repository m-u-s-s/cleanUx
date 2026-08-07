export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  /**
   * `mode` prépositionne le créneau : `asap` pour une intervention immédiate, `scheduled` pour
   * un rendez-vous. Optionnel — sans lui, le parcours se comporte comme avant.
   */
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
  /*
   * Espace SOCIÉTÉ CLIENTE, en natif. Il n'existait que sur le web : `routes/api/client.php`
   * n'exposait que l'annuaire des sociétés prestataires et les réservations. L'API
   * `/client/company/*` a été créée avec ces écrans.
   */
  CompanySites: undefined;
  CompanySigningAppointments: undefined;
  CompanyMultiSiteRequest: undefined;
};


export type TabParamList = {
  Home: undefined;
  Explore: undefined;
  Bookings: undefined;
  Profile: undefined;
};
