import type { NavigatorScreenParams } from '@react-navigation/native';
import type { AdminTabParamList } from '@/admin/types';

export type RootStackParamList = {
  Login: undefined;
  // MainTabs nests TabParamList — declaring it `undefined` hid the fact that reaching a
  // tab (e.g. Earnings) requires `{ screen: '<Tab>' }`.
  MainTabs: NavigatorScreenParams<TabParamList> | undefined;
  // Parcours de vérification : seul écran atteignable tant que le dossier est incomplet.
  ProviderOnboarding: undefined;
  /**
   * Console d'administration. Rendue dans une pile SÉPARÉE de celle du prestataire : aucun écran
   * prestataire ne concerne un administrateur, et les y laisser atteignables donnerait des routes
   * qui répondent 403 à qui les ouvre.
   */
  AdminSpace: NavigatorScreenParams<AdminTabParamList> | undefined;
  AdminResource: { moduleKey: string; title: string };
  /** Le moteur de console — trois écrans qui servent tous les domaines décrits. */
  AdminResourceList: { resource: string; title: string };
  /** Un module servi comme synthèse : des tuiles chiffrées, pas une liste. */
  AdminReport: { report: string; title: string };
  // La descente géographique du catalogue : zones d'un pays, puis métiers d'une zone.
  AdminCatalogZones: { countryId: number; title?: string };
  AdminCatalogTrades: { zoneId: number; title?: string };
  AdminResourceDetail: { resource: string; title: string; id: string | number };
  AdminResourceForm: {
    resource: string;
    title: string;
    id?: string | number;
    // Valeurs imposées par le contexte : le pays d'où l'on crée une zone.
    prefill?: Record<string, unknown>;
  };
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
