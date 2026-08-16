import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { useOnboardingProgress, isJourneyComplete } from '@/onboarding';
import { useFaceCheckStatus, faceCheckBloqueLeTerrain } from '@/faceCheck';
import { LoginScreen } from '@/screens/LoginScreen';
import { MissionDetailScreen } from '@/screens/MissionDetailScreen';
import { MissionInboxScreen } from '@/screens/MissionInboxScreen';
import { MissionFieldScreen } from '@/screens/MissionFieldScreen';
import { PresenceScanScreen } from '@/screens/PresenceScanScreen';
import { TrackingScreen } from '@/screens/TrackingScreen';
import { StripeOnboardingScreen } from '@/screens/StripeOnboardingScreen';
import { AvailabilityScreen } from '@/screens/AvailabilityScreen';
import { TradesZonesScreen } from '@/screens/TradesZonesScreen';
import { BadgesScreen } from '@/screens/BadgesScreen';
import { SafetyScreen } from '@/screens/SafetyScreen';
import { DailyRouteScreen } from '@/screens/DailyRouteScreen';
import { KYCScreen } from '@/screens/KYCScreen';
import { ProviderDisputesScreen } from '@/screens/ProviderDisputesScreen';
import { ProviderRatingsScreen } from '@/screens/ProviderRatingsScreen';
import { ProviderChatListScreen } from '@/screens/ProviderChatListScreen';
import { ProviderChatScreen } from '@/screens/ProviderChatScreen';
import { ProviderNotificationsScreen } from '@/screens/ProviderNotificationsScreen';
import { ProviderNotificationDetailScreen } from '@/screens/ProviderNotificationDetailScreen';
import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { LegalScreen } from '@/screens/LegalScreen';
import { EmbeddedModuleRoute } from '@/screens/EmbeddedModuleRoute';
import { CompanyMembersScreen } from '@/screens/company/CompanyMembersScreen';
import { CompanyFieldTeamsScreen } from '@/screens/company/CompanyFieldTeamsScreen';
import { CompanyTasksScreen } from '@/screens/company/CompanyTasksScreen';
import { CompanyDispatchScreen } from '@/screens/company/CompanyDispatchScreen';
import { CompanyChannelsScreen } from '@/screens/company/CompanyChannelsScreen';
import { CompanyRolePermissionsScreen } from '@/screens/company/CompanyRolePermissionsScreen';
import { CompanyMissionDetailScreen } from '@/screens/company/CompanyMissionDetailScreen';
import { CompanyAgenciesScreen } from '@/screens/company/CompanyAgenciesScreen';
import { CompanyPlanningScreen } from '@/screens/company/CompanyPlanningScreen';
import { CompanyTimesheetsScreen } from '@/screens/company/CompanyTimesheetsScreen';
import { CompanyInventoryScreen } from '@/screens/company/CompanyInventoryScreen';
import { CompanyQuotesScreen } from '@/screens/company/CompanyQuotesScreen';
import { CompanyRecruitmentScreen } from '@/screens/company/CompanyRecruitmentScreen';
import { CompanyQualityFleetScreen } from '@/screens/company/CompanyQualityFleetScreen';
import { ChannelConversationScreen } from '@/screens/company/ChannelConversationScreen';
import { CallScreen } from '@/screens/company/CallScreen';
import { CompanySitesScreen } from '@/screens/company/CompanySitesScreen';
// Polish — UX screens
import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { AppearanceScreen } from '@/screens/AppearanceScreen';
import { ProviderOnboardingScreen } from '@/screens/onboarding/ProviderOnboardingScreen';
import FaceCheckScreen from '@/screens/faceCheck/FaceCheckScreen';
// Espace d'administration — l'application prestataire sert deux publics depuis le lot A.
import { SpaceSwitcherScreen } from '@/screens/SpaceSwitcherScreen';
// Espace société — le troisième public : celui qui pilote une société prestataire.
import { ProviderCompanyNavigator } from '@/company/ProviderCompanyNavigator';
import { AdminNavigator } from '@/admin/AdminNavigator';
import { SuperAdminHomeScreen } from '@/admin/SuperAdminHomeScreen';
import { ModulesRoute } from '@/screens/ModulesRoute';
import { CatalogZonesScreen } from '@/admin/catalogue/CatalogZonesScreen';
import { CatalogZoneTradesScreen } from '@/admin/catalogue/CatalogZoneTradesScreen';
import { JourneyBuilderScreen } from '@/admin/catalogue/JourneyBuilderScreen';
import { ResourcePickerScreen } from '@/admin/console/ResourcePickerScreen';
import { AdminResourceScreen } from '@/admin/AdminResourceScreen';
import { ResourceListScreen } from '@/admin/console/ResourceListScreen';
import { ResourceDetailScreen } from '@/admin/console/ResourceDetailScreen';
import { ResourceFormScreen } from '@/admin/console/ResourceFormScreen';
import { ReportScreen } from '@/admin/console/ReportScreen';
import { resolveSpace } from '@/admin/space';
import { useSpacePreference } from '@/admin/useSpacePreference';
import { TabNavigator } from './TabNavigator';
import { AsapOffersScreen } from '@/asap';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const theme = useThemeColors();
  const { user, isAuthenticated, isLoading } = useAuth();

  // Le parcours de vérification garde l'entrée de l'application. Sans lui, un compte tout juste
  // créé atterrissait sur le tableau de bord où chaque appel échouait en 403, sans explication.
  // La requête n'est lancée qu'une fois authentifié — l'endpoint l'exige.
  const { data: onboarding, isLoading: onboardingLoading, isError: onboardingError } =
    useOnboardingProgress(isAuthenticated);

  // L'espace retenu d'un compte à double casquette. Tant qu'il se lit, on n'ouvre rien : ouvrir
  // l'espace par défaut le temps d'une lecture asynchrone ferait clignoter un écran qui n'est pas
  // le sien.
  const { space: chosenSpace, isLoading: spaceLoading, choose } = useSpacePreference();

  // L'état du dossier, réduit à ce que l'aiguillage doit en savoir. `undefined` = inconnu, et
  // l'inconnu laisse passer.
  const onboardingComplete =
    onboardingLoading || onboardingError ? undefined : isJourneyComplete(onboarding);

  /*
   * L'état du contrôle facial, réduit lui aussi à ce que l'aiguillage doit en savoir.
   *
   * Même règle que le dossier : `undefined` tant qu'on charge ou qu'on a échoué, et l'inconnu
   * laisse passer. La requête n'est lancée qu'une fois authentifié — l'endpoint l'exige — et elle
   * rend `required: false` en une seule ligne pour les prestataires hors périmètre, ce qui est le
   * cas de l'immense majorité d'entre eux.
   */
  const { data: faceStatus, isLoading: faceLoading, isError: faceError } =
    useFaceCheckStatus(isAuthenticated);

  const faceCheckBlocks =
    faceLoading || faceError ? undefined : faceCheckBloqueLeTerrain(faceStatus);

  const space = resolveSpace({
    isLoading: isLoading || spaceLoading,
    isAuthenticated,
    user,
    onboardingComplete,
    faceCheckBlocks,
    chosenSpace,
  });

  if (space === 'loading') {
    return (
      <View
        testID="root-navigator"
        style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.page }}
      >
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  /*
   * L'espace d'administration court-circuite la pile prestataire entière.
   *
   * Il est rendu HORS du `Stack.Navigator` ci-dessous parce qu'aucun de ses écrans ne concerne un
   * administrateur : les y laisser atteignables donnerait des routes qui répondent 403 à qui les
   * ouvre. Le choix d'espace passe par le même chemin, pour la même raison.
   */
  if (space === 'switcher') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <SpaceSwitcherScreen
          onChoose={(next) => void choose(next)}
          peutPiloterLaSociete={user?.can_manage_company === true}
        />
      </View>
    );
  }

  /*
   * L'ESPACE SOCIÉTÉ PRESTATAIRE, rendu HORS de la pile terrain — pour la même raison que
   * l'administration, et une de plus.
   *
   * La raison commune : aucun écran de la pile terrain ne concerne un gérant, et les y laisser
   * atteignables donnerait des routes qui répondent 403 à qui les ouvre.
   *
   * La raison propre : `TabNavigator` est l'unique point de montage de `usePresenceHeartbeat()`.
   * Rendre l'espace société à l'intérieur ferait battre le cœur de présence d'un gérant assis à son
   * bureau — il apparaîtrait DISPONIBLE dans le dispatch de sa propre société, et le moteur
   * d'affectation lui proposerait des missions qu'il ne fera pas.
   *
   * `CompanyMembers` et les écrans de réglages restent sur cette pile : ils se poussent par-dessus
   * les onglets, avec un retour, comme partout ailleurs.
   */
  if (space === 'providerCompany') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          <Stack.Screen name="ProviderCompanySpace" component={ProviderCompanyNavigator} />
          <Stack.Screen
            name="Modules"
            component={ModulesRoute}
            options={{ headerShown: true, title: 'Modules' }}
          />
          <Stack.Screen
            name="CompanyMembers"
            component={CompanyMembersScreen}
            options={{ headerShown: true, title: 'Équipe' }}
          />
          <Stack.Screen
            name="CompanySites"
            component={CompanySitesScreen}
            options={{ headerShown: true, title: 'Sites desservis' }}
          />
          <Stack.Screen
            name="CompanyRolePermissions"
            component={CompanyRolePermissionsScreen}
            options={{ headerShown: true, title: 'Rôles et permissions' }}
          />
          <Stack.Screen
            name="CompanyMissionDetail"
            component={CompanyMissionDetailScreen}
            options={{ headerShown: true, title: 'Mission' }}
          />
          <Stack.Screen
            name="CompanyAgencies"
            component={CompanyAgenciesScreen}
            options={{ headerShown: true, title: 'Implantations' }}
          />
          <Stack.Screen
            name="CompanyPlanning"
            component={CompanyPlanningScreen}
            options={{ headerShown: true, title: 'Planning et absences' }}
          />
          <Stack.Screen
            name="CompanyTimesheets"
            component={CompanyTimesheetsScreen}
            options={{ headerShown: true, title: 'Heures et rentabilité' }}
          />
          <Stack.Screen
            name="CompanyInventory"
            component={CompanyInventoryScreen}
            options={{ headerShown: true, title: 'Consommables' }}
          />
          <Stack.Screen
            name="CompanyQuotes"
            component={CompanyQuotesScreen}
            options={{ headerShown: true, title: 'Devis' }}
          />
          <Stack.Screen
            name="CompanyRecruitment"
            component={CompanyRecruitmentScreen}
            options={{ headerShown: true, title: 'Recrutement' }}
          />
          <Stack.Screen
            name="CompanyQualityFleet"
            component={CompanyQualityFleetScreen}
            options={{ headerShown: true, title: 'Qualité et matériel' }}
          />
          <Stack.Screen
            name="DailyRoute"
            component={DailyRouteScreen}
            options={{ headerShown: true, title: 'Ma journée' }}
          />
          <Stack.Screen
            name="Safety"
            component={SafetyScreen}
            options={{ headerShown: true, title: 'Sécurité' }}
          />
          <Stack.Screen
            name="ChannelConversation"
            component={ChannelConversationScreen}
            options={{ headerShown: true, title: 'Conversation' }}
          />
          <Stack.Screen
            name="Call"
            component={CallScreen}
            options={{ headerShown: true, title: 'Appel' }}
          />
          <Stack.Screen
            name="CompanyDispatch"
            component={CompanyDispatchScreen}
            options={{ headerShown: true, title: 'Répartition' }}
          />
          <Stack.Screen
            name="ProviderChat"
            component={ProviderChatScreen}
            options={({ route }) => ({ headerShown: true, title: (route.params as any).title ?? 'Chat' })}
          />
          <Stack.Screen
            name="ProviderNotifications"
            component={ProviderNotificationsScreen}
            options={{ headerShown: true, title: 'Notifications' }}
          />
          <Stack.Screen
            name="ProviderNotificationDetail"
            component={ProviderNotificationDetailScreen}
            options={{ headerShown: true, title: 'Notification' }}
          />
          {/*
            L'ISSUE VERS L'ESPACE TERRAIN EST UN ONGLET, PAS UNE ROUTE DÉCLARÉE ICI.

            Elle a d'abord été écrite ainsi — `<Stack.Screen name="Profile" ...>` — avec le
            commentaire qui convenait sur l'enfermement. Elle n'a jamais fonctionné : aucun
            `navigate('Profile')` n'existait dans cette application et la barre à cinq onglets n'en
            parlait pas, si bien que la route restait montée et joignable par personne.

            Elle aurait de toute façon mal fini si on l'avait atteinte : `ProfileScreen` ouvre les
            disponibilités, les badges, le KYC — aucune de ces routes n'est montée sur cette pile.
            Voir `CompanyProfileScreen`, qui porte la sortie depuis la barre, et qui écrit
            « terrain » plutôt que d'effacer le choix.
          */}
          <Stack.Screen
            name="NotificationPreferences"
            component={NotificationPreferencesScreen}
            options={{ title: 'Préférences notifications', headerShown: true }}
          />
          <Stack.Screen
            name="Language"
            component={LanguageScreen}
            options={{ title: 'Langue', headerShown: true }}
          />
          <Stack.Screen
            name="Appearance"
            component={AppearanceScreen}
            options={{ title: 'Apparence', headerShown: true }}
          />
          <Stack.Screen
            name="EmbeddedModule"
            component={EmbeddedModuleRoute}
            options={{ headerShown: true }}
          />
          <Stack.Screen
            name="Legal"
            component={LegalScreen}
            options={({ route }) => ({
              title: route.params.type === 'terms' ? 'CGU' : 'Confidentialité',
              headerShown: true,
            })}
          />
        </Stack.Navigator>
      </View>
    );
  }

  /*
   * L'ESPACE DU SUPER ADMINISTRATEUR, rendu hors des deux autres piles.
   *
   * Même raison que pour l'administration : aucun écran de la pile terrain ne le concerne. Et une
   * de plus — il ne peut pas vivre DANS la console, puisque c'est précisément ce dont il doit se
   * distinguer. `is_admin` étant vrai pour lui, le monter là reviendrait à ne rien distinguer.
   *
   * Les réglages restent montés sur cette pile : l'écran y navigue, et une route absente ne lève
   * rien — elle ne fait simplement rien, ce qui rend ce genre de lien mort très discret.
   */
  if (space === 'superAdmin') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          <Stack.Screen name="SuperAdminSpace" component={SuperAdminHomeScreen} />
          <Stack.Screen
            name="Modules"
            component={ModulesRoute}
            options={{ headerShown: true, title: 'Modules' }}
          />
          <Stack.Screen
            name="Appearance"
            component={AppearanceScreen}
            options={{ title: 'Apparence', headerShown: true }}
          />
          <Stack.Screen
            name="Language"
            component={LanguageScreen}
            options={{ title: 'Langue', headerShown: true }}
          />
          <Stack.Screen
            name="NotificationPreferences"
            component={NotificationPreferencesScreen}
            options={{ title: 'Préférences notifications', headerShown: true }}
          />
        </Stack.Navigator>
      </View>
    );
  }

  if (space === 'admin') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          <Stack.Screen name="AdminSpace" component={AdminNavigator} />
          <Stack.Screen
            name="Modules"
            component={ModulesRoute}
            options={{ headerShown: true, title: 'Modules' }}
          />
          {/*
            Le moteur de console : trois écrans pour tous les domaines. `AdminResource` reste
            monté en dernier recours — il n'est atteignable que si un module est déclaré couvert
            sans que le moteur sache le servir, et il le dit alors plutôt que de faire tomber
            l'application sur une route inconnue.
          */}
          <Stack.Screen
            name="AdminResourceList"
            component={ResourceListScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Module',
            })}
          />
          {/*
            La descente géographique du catalogue. Les deux niveaux profonds vivent sur la pile
            RACINE et non dans les onglets : ils se poussent par-dessus, avec un retour, comme
            partout ailleurs dans la console. Les monter dans l'onglet ferait disparaître la barre.
          */}
          <Stack.Screen
            name="AdminCatalogZones"
            component={CatalogZonesScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Zones',
            })}
          />
          <Stack.Screen
            name="AdminTradeJourney"
            component={JourneyBuilderScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Parcours',
            })}
          />
          <Stack.Screen
            name="AdminCatalogTrades"
            component={CatalogZoneTradesScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Catalogue',
            })}
          />
          <Stack.Screen
            name="AdminResourcePicker"
            component={ResourcePickerScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Module',
            })}
          />
          <Stack.Screen
            name="AdminReport"
            component={ReportScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Synthèse',
            })}
          />
          <Stack.Screen
            name="AdminResourceDetail"
            component={ResourceDetailScreen}
            options={{ headerShown: true, title: 'Détail' }}
          />
          <Stack.Screen
            name="AdminResourceForm"
            component={ResourceFormScreen}
            options={{ headerShown: true, title: 'Formulaire' }}
          />
          <Stack.Screen
            name="AdminResource"
            component={AdminResourceScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Module',
            })}
          />
          <Stack.Screen
            name="Legal"
            component={LegalScreen}
            options={({ route }) => ({
              title: route.params.type === 'terms' ? 'CGU' : 'Confidentialité',
              headerShown: true,
            })}
          />
        </Stack.Navigator>
      </View>
    );
  }

  return (
    <View testID="root-navigator" style={{ flex: 1 }}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {space === 'providerOnboarding' ? (
          // Dossier incomplet : rien d'autre n'est atteignable. Une ERREUR de chargement laisse
          // en revanche passer — mieux vaut un dashboard partiellement bloqué par le serveur
          // qu'un utilisateur enfermé hors de son app parce qu'une requête a échoué.
          <Stack.Screen name="ProviderOnboarding" component={ProviderOnboardingScreen} />
        ) : space === 'faceCheck' ? (
          /*
           * CONTRÔLE D'IDENTITÉ EN COURS : le MÊME court-circuit de pile.
           *
           * Un seul écran monté, donc littéralement nulle part où aller. Une modale par-dessus les
           * onglets se contournerait en changeant d'onglet ; c'est la pile elle-même qui doit être
           * vide, comme pour le dossier incomplet.
           *
           * Et comme lui : l'inconnu laisse passer. Une requête de statut qui échoue ne doit pas
           * enfermer un prestataire hors de son application — le serveur refusera de toute façon
           * la mise en ligne, l'acceptation et le départ.
           */
          <Stack.Screen name="FaceCheck" component={FaceCheckScreen} />
        ) : isAuthenticated ? (
          <>
            <Stack.Screen name="MainTabs" component={TabNavigator} />
            <Stack.Screen
              name="Modules"
              component={ModulesRoute}
              options={{ headerShown: true, title: 'Modules' }}
            />
            {/*
              Les courses immédiates. Les points d'API existaient depuis la livraison du moteur
              de commande sans que rien ne les appelle : un client pouvait demander une
              intervention dans l'heure, et aucun prestataire ne pouvait l'accepter.
            */}
            <Stack.Screen
              name="AsapOffers"
              component={AsapOffersScreen}
              options={{ title: 'Courses immédiates', headerShown: true }}
            />
            <Stack.Screen
              name="MissionDetail"
              component={MissionDetailScreen}
              options={{ headerShown: true, title: 'Mission' }}
            />
            <Stack.Screen
              name="MissionInbox"
              component={MissionInboxScreen}
              options={{ headerShown: true, title: 'Missions disponibles' }}
            />
            <Stack.Screen
              name="MissionField"
              component={MissionFieldScreen}
              options={{ headerShown: true, title: 'Mission terrain' }}
            />
            <Stack.Screen
              name="MissionTracking"
              component={TrackingScreen}
              options={{ headerShown: true, title: 'Suivi GPS' }}
            />
            <Stack.Screen
              name="PresenceScan"
              component={PresenceScanScreen}
              options={{ headerShown: true, title: 'Confirmer ma présence' }}
            />
            <Stack.Screen
              name="StripeOnboarding"
              component={StripeOnboardingScreen}
              options={{ headerShown: true, title: 'Stripe Connect' }}
            />
            <Stack.Screen
              name="Availability"
              component={AvailabilityScreen}
              options={{ headerShown: true, title: 'Disponibilités' }}
            />
            {/*
              « CE QUE JE FAIS, ET OÙ ».

              L'écran décide de ce que le prestataire reçoit : ses métiers et ses zones sont les
              deux tables que lit la requête candidate du dispatch. Il n'existait pas — le métier se
              déclarait une fois à l'inscription et ne se modifiait plus, les zones nulle part.
            */}
            <Stack.Screen
              name="TradesZones"
              component={TradesZonesScreen}
              options={{ headerShown: true, title: 'Métiers et zones' }}
            />
            <Stack.Screen
              name="Badges"
              component={BadgesScreen}
              options={{ headerShown: true, title: 'Mes badges' }}
            />
            <Stack.Screen
              name="KYC"
              component={KYCScreen}
              options={{ headerShown: true, title: 'Vérification identité' }}
            />
            <Stack.Screen
              name="ProviderDisputes"
              component={ProviderDisputesScreen}
              options={{ headerShown: true, title: 'Litiges' }}
            />
            <Stack.Screen
              name="ProviderRatings"
              component={ProviderRatingsScreen}
              options={{ headerShown: true, title: 'Avis reçus' }}
            />
            <Stack.Screen
              name="ProviderChatList"
              component={ProviderChatListScreen}
              options={{ headerShown: true, title: 'Messagerie' }}
            />
            <Stack.Screen
              name="ProviderChat"
              component={ProviderChatScreen}
              options={({ route }) => ({ headerShown: true, title: (route.params as any).title ?? 'Chat' })}
            />
            <Stack.Screen
              name="ProviderNotifications"
              component={ProviderNotificationsScreen}
              options={{ headerShown: true, title: 'Notifications' }}
            />
            <Stack.Screen
              name="ProviderNotificationDetail"
              component={ProviderNotificationDetailScreen}
              options={{ headerShown: true, title: 'Notification' }}
            />
            {/*
              L'espace société, servi par l'hôte WebView partagé. Le titre natif est posé par
              l'écran lui-même (useLayoutEffect) à partir du module demandé.
            */}
            <Stack.Screen
              name="EmbeddedModule"
              component={EmbeddedModuleRoute}
              options={{ headerShown: true }}
            />
            {/* Espace société — écrans natifs, servis par l'API /provider/company/*. */}
            <Stack.Screen
              name="CompanyMembers"
              component={CompanyMembersScreen}
              options={{ headerShown: true, title: 'Équipe' }}
            />
            <Stack.Screen
              name="CompanyFieldTeams"
              component={CompanyFieldTeamsScreen}
              options={{ headerShown: true, title: 'Équipes terrain' }}
            />
            <Stack.Screen
              name="CompanyTasks"
              component={CompanyTasksScreen}
              options={{ headerShown: true, title: 'Tâches' }}
            />
            <Stack.Screen
              name="CompanyDispatch"
              component={CompanyDispatchScreen}
              options={{ headerShown: true, title: 'Répartition' }}
            />
            <Stack.Screen
              name="CompanyChannels"
              component={CompanyChannelsScreen}
              options={{ headerShown: true, title: 'Canaux' }}
            />
            <Stack.Screen
              name="CompanySites"
              component={CompanySitesScreen}
              options={{ headerShown: true, title: 'Sites desservis' }}
            />
            <Stack.Screen
              name="CompanyRolePermissions"
              component={CompanyRolePermissionsScreen}
              options={{ headerShown: true, title: 'Rôles et permissions' }}
            />
            <Stack.Screen
              name="CompanyMissionDetail"
              component={CompanyMissionDetailScreen}
              options={{ headerShown: true, title: 'Mission' }}
            />
            <Stack.Screen
              name="CompanyAgencies"
              component={CompanyAgenciesScreen}
              options={{ headerShown: true, title: 'Implantations' }}
            />
            <Stack.Screen
              name="CompanyPlanning"
              component={CompanyPlanningScreen}
              options={{ headerShown: true, title: 'Planning et absences' }}
            />
            <Stack.Screen
              name="CompanyTimesheets"
              component={CompanyTimesheetsScreen}
              options={{ headerShown: true, title: 'Heures et rentabilité' }}
            />
            <Stack.Screen
              name="CompanyInventory"
              component={CompanyInventoryScreen}
              options={{ headerShown: true, title: 'Consommables' }}
            />
            <Stack.Screen
              name="CompanyQuotes"
              component={CompanyQuotesScreen}
              options={{ headerShown: true, title: 'Devis' }}
            />
            <Stack.Screen
              name="CompanyRecruitment"
              component={CompanyRecruitmentScreen}
              options={{ headerShown: true, title: 'Recrutement' }}
            />
            <Stack.Screen
              name="CompanyQualityFleet"
              component={CompanyQualityFleetScreen}
              options={{ headerShown: true, title: 'Qualité et matériel' }}
            />
            <Stack.Screen
              name="DailyRoute"
              component={DailyRouteScreen}
              options={{ headerShown: true, title: 'Ma journée' }}
            />
            <Stack.Screen
              name="Safety"
              component={SafetyScreen}
              options={{ headerShown: true, title: 'Sécurité' }}
            />
            <Stack.Screen
              name="ChannelConversation"
              component={ChannelConversationScreen}
              options={{ headerShown: true, title: 'Conversation' }}
            />
            <Stack.Screen
              name="Call"
              component={CallScreen}
              options={{ headerShown: true, title: 'Appel' }}
            />
            <Stack.Screen
              name="Legal"
              component={LegalScreen}
              options={({ route }) => ({
                title: route.params.type === 'terms' ? 'CGU' : 'Confidentialité',
                headerShown: true,
              })}
            />
            {/* Polish — UX screens */}
            <Stack.Screen
              name="NotificationPreferences"
              component={NotificationPreferencesScreen}
              options={{ title: 'Préférences notifications', headerShown: true }}
            />
            <Stack.Screen
              name="Language"
              component={LanguageScreen}
              options={{ title: 'Langue', headerShown: true }}
            />
            <Stack.Screen
              name="Appearance"
              component={AppearanceScreen}
              options={{ title: 'Apparence', headerShown: true }}
            />
          </>
        ) : (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} options={{ title: 'Mot de passe oublié', headerShown: true }} />
            <Stack.Screen name="Legal" component={LegalScreen} options={({ route }) => ({ title: route.params.type === 'terms' ? 'CGU' : 'Confidentialité', headerShown: true })} />
          </>
        )}
      </Stack.Navigator>
    </View>
  );
}
