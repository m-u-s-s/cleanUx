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
   * L'ESPACE SOCIÉTÉ CLIENTE, EN NATIF.
   *
   * `config/parity.php` déclarait ces six modules en `mobile => 'webview'`, mais l'application ne
   * les servait sous aucune forme : ni écran, ni lien, et `ModuleHubScreen` — seule porte générique
   * vers les modules web — monté dans aucun navigateur. Ils sont désormais natifs et servis par
   * `/api/client/company/*`, créée avec eux.
   *
   * `CompanyOverview` est la table des matières des cinq autres : déclarer une route sans qu'un
   * chemin y mène est le défaut même qu'on corrige ici.
   */
  CompanyOverview: undefined;
  CompanySites: undefined;
  CompanyBookings: undefined;
  CompanyMembers: undefined;
  CompanyContracts: undefined;
  CompanyBilling: undefined;
};


export type TabParamList = {
  Home: undefined;
  Explore: undefined;
  Bookings: undefined;
  Profile: undefined;
};
