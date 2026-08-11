export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
  /**
   * `mode` prépositionne le créneau : `asap` pour une intervention immédiate, `scheduled` pour
   * un rendez-vous. Optionnel — sans lui, le parcours se comporte comme avant.
   */
  MissionTracking: { bookingId: number };
  /** Le déroulé de l'intervention chez soi : avancement, photos avant/après, imprévus. */
  OnSite: { bookingId: number };
  BookingDetail: { bookingId: number };
  QRScan: { bookingId: number; action: 'start' | 'end' };
  PaymentCheckout: { bookingId: number };
  SavedPaymentMethods: undefined;
  Chat: { threadId: number; title: string };
  ChatList: undefined;
  Notifications: undefined;
  // Sprint 9
  Rating: { bookingId: number };
  /**
   * Les devis reçus d'une société prestataire (E24).
   *
   * Le client découvre le document par une notification POUSSÉE SUR SON TÉLÉPHONE : sans cet
   * écran, il devrait ouvrir un ordinateur pour dire oui. C'est exactement le point où une
   * affaire se perd.
   */
  /**
   * Le carnet de lieux (E2).
   *
   * C'est SUR PLACE qu'on note le digicode qu'on vient de composer, l'étage, la clé chez la
   * voisine du deuxième. Renvoyer cette saisie à un ordinateur revient à ne jamais la faire.
   */
  Places: undefined;
  /** Le budget entretien (E4) — la question se pose souvent en recevant une facture, sur mobile. */
  Budget: undefined;
  /**
   * « Ma protection » (E6). Elle se consulte au pire moment : quand quelque chose vient de se
   * casser, et qu'on n'est pas devant un ordinateur.
   */
  Protection: undefined;
  ReceivedQuotes: undefined;
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
  /** Le répertoire des modules du rôle — catalogue servi par `/api/modules`. */
  Modules: undefined;
  /*
   * L'ESPACE SOCIÉTÉ CLIENTE, EN NATIF.
   *
   * `config/parity.php` déclarait ces six modules en `mobile => 'webview'`, mais l'application ne
   * les servait sous aucune forme : ni écran, ni lien, et `ModuleHubScreen` — seule porte générique
   * vers les modules web — monté dans aucun navigateur. Ils sont désormais natifs et servis par
   * `/api/client/company/*`, créée avec eux. Ce hub a été supprimé depuis, devenu sans objet.
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
  /**
   * L'espace société complet, en onglets — la maison d'un responsable de sites, pas un bouton
   * enfoui dans le profil. Rendu HORS de la pile personnelle : voir `RootNavigator`.
   */
  ClientCompanySpace: undefined;
};


export type TabParamList = {
  Home: undefined;
  Explore: undefined;
  Bookings: undefined;
  Profile: undefined;
};

/**
 * Les onglets de l'ESPACE SOCIÉTÉ, distincts de ceux du compte personnel.
 *
 * Suffixe `Tab` pour ne pas entrer en collision avec les routes de même nom sur la pile racine :
 * `CompanySites` y reste déclarée, atteignable depuis le profil par un membre de société qui
 * travaille dans son espace perso.
 */
export type ClientCompanyTabParamList = {
  CompanyOverviewTab: undefined;
  CompanySitesTab: undefined;
  CompanyBookingsTab: undefined;
  CompanyBillingTab: undefined;
  CompanyProfileTab: undefined;
};
