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
  /** L'espace du sixième rôle. Il n'a qu'un écran : la structure des rôles, et les portes. */
  SuperAdminSpace: undefined;
  AdminSpace: NavigatorScreenParams<AdminTabParamList> | undefined;
  AdminResource: { moduleKey: string; title: string };
  /** Le moteur de console — trois écrans qui servent tous les domaines décrits. */
  AdminResourceList: { resource: string; title: string };
  /** Un module servi comme synthèse : des tuiles chiffrées, pas une liste. */
  AdminReport: { report: string; title: string };
  // La descente géographique du catalogue : zones d'un pays, puis métiers d'une zone.
  AdminCatalogZones: { countryId: number; title?: string };
  AdminCatalogTrades: { zoneId: number; title?: string };
  // Le constructeur de parcours d'un métier : questions, réponses et leurs suppléments.
  AdminTradeJourney: { tradeId: number; title?: string };
  AdminResourceDetail: { resource: string; title: string; id: string | number };
  // Le choix entre les ressources d'un module multi-modèles.
  AdminResourcePicker: { title: string; resources: string[] };
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
  /** « Ce que je fais, et où » : les deux tables que lit la requête candidate du dispatch. */
  TradesZones: undefined;
  Badges: undefined;
  ProviderDisputes: undefined;
  ProviderRatings: undefined;
  ProviderChatList: undefined;
  ProviderChat: { threadId: number; title: string };
  ProviderNotifications: undefined;
  /**
   * Un module web servi dans l'hôte WebView partagé.
   *
   * Porte d'entrée de l'espace société sur mobile : répartition, équipes terrain, canaux et
   * membres restaient jusqu'ici réservés au navigateur. La migration vers du natif se fera écran
   * par écran, sans bloquer l'accès en attendant.
   */
  EmbeddedModule: { path: string; title: string };
  /** Le répertoire des modules du rôle — catalogue servi par `/api/modules`. */
  Modules: undefined;
  /*
   * Espace société, en NATIF. Ces trois écrans étaient servis par `EmbeddedModule` faute d'API :
   * `routes/api/provider.php` couvrait le prestataire individuel et rien de la société. L'API
   * `/provider/company/*` créée avec eux lève cet obstacle.
   *
   * Les CINQ écrans de l'espace société sont désormais natifs. `EmbeddedModule` reste monté : il
   * sert d'issue pour tout module web qu'on voudrait embarquer sans écrire d'API, et son hôte est
   * partagé avec l'application cliente.
   */
  CompanyMembers: undefined;
  CompanyFieldTeams: undefined;
  CompanyTasks: undefined;
  CompanyDispatch: undefined;
  CompanyChannels: undefined;
  /** Les sites clients desservis, en lecture — la désignation d'un référent se pose au bureau. */
  CompanySites: undefined;
  /**
   * La matrice rôle → permissions PROPRE à la société.
   *
   * « Chez nous, les chefs d'équipe assignent les missions. » Ce réglage n'existait sur aucune
   * surface : la table était lue par le serveur et écrite par personne.
   */
  CompanyRolePermissions: undefined;
  /**
   * Le détail d'une mission de société : qui est libre, assigner, renforts.
   *
   * Ce qui existait tenait dans un `Alert.alert` limité à dix noms, sans indicateur de
   * disponibilité — le répartiteur choisissait à l'aveugle depuis son téléphone.
   */
  CompanyMissionDetail: { missionId: number };
  /**
   * Les implantations de la SOCIETE — a distinguer des locaux du CLIENT (`CompanySites`).
   * Une societe multi-villes n'avait aucun moyen de declarer son depot de Bruxelles.
   */
  CompanyAgencies: undefined;
  /**
   * Une conversation d'equipe — temps reel, participants, note vocale.
   *
   * `CompanyChannelsScreen` melait la liste et le fil dans un seul ecran, SANS temps reel : il
   * fallait tirer pour rafraichir, ce qui d'une messagerie fait un formulaire.
   */
  ChannelConversation: { channelId: number };
  /**
   * Un appel en cours. Le JETON se demande, il ne se recoit pas : la banniere diffusee sur
   * `channel.{id}` ne porte que l'identifiant, sinon tous les membres pourraient entrer dans la
   * salle sans avoir decroche.
   */
  Call: { callId: number; video?: boolean };
  /**
   * L'espace société complet, en onglets — la maison d'un gérant, pas une liste de boutons dans le
   * profil. Rendu hors de la pile terrain : voir `RootNavigator`, qui explique pourquoi le
   * battement de présence ne doit PAS y être monté.
   */
  ProviderCompanySpace: undefined;
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

/**
 * Les onglets de l'ESPACE SOCIÉTÉ, distincts de ceux du terrain.
 *
 * Les noms portent le suffixe `Tab` pour ne pas entrer en collision avec les routes de même nom sur
 * la pile racine : `CompanyDispatch` y reste déclaré, atteignable depuis le profil par un gérant
 * qui travaille dans l'espace terrain.
 */
export type ProviderCompanyTabParamList = {
  CompanyOverviewTab: undefined;
  CompanyDispatchTab: undefined;
  CompanyFieldTeamsTab: undefined;
  CompanyTasksTab: undefined;
  CompanyChannelsTab: undefined;
  CompanyProfileTab: undefined;
};
