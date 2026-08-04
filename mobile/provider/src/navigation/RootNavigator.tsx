import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { useOnboardingProgress, isJourneyComplete } from '@/onboarding';
import { LoginScreen } from '@/screens/LoginScreen';
import { MissionDetailScreen } from '@/screens/MissionDetailScreen';
import { MissionInboxScreen } from '@/screens/MissionInboxScreen';
import { MissionFieldScreen } from '@/screens/MissionFieldScreen';
import { PresenceScanScreen } from '@/screens/PresenceScanScreen';
import { TrackingScreen } from '@/screens/TrackingScreen';
import { StripeOnboardingScreen } from '@/screens/StripeOnboardingScreen';
import { AvailabilityScreen } from '@/screens/AvailabilityScreen';
import { BadgesScreen } from '@/screens/BadgesScreen';
import { KYCScreen } from '@/screens/KYCScreen';
import { ProviderDisputesScreen } from '@/screens/ProviderDisputesScreen';
import { ProviderRatingsScreen } from '@/screens/ProviderRatingsScreen';
import { ProviderChatListScreen } from '@/screens/ProviderChatListScreen';
import { ProviderChatScreen } from '@/screens/ProviderChatScreen';
import { ProviderNotificationsScreen } from '@/screens/ProviderNotificationsScreen';
import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { LegalScreen } from '@/screens/LegalScreen';
// Polish — UX screens
import { NotificationPreferencesScreen } from '@/screens/NotificationPreferencesScreen';
import { LanguageScreen } from '@/screens/LanguageScreen';
import { AppearanceScreen } from '@/screens/AppearanceScreen';
import { ProviderOnboardingScreen } from '@/screens/onboarding/ProviderOnboardingScreen';
// Espace d'administration — l'application prestataire sert deux publics depuis le lot A.
import { SpaceSwitcherScreen } from '@/screens/SpaceSwitcherScreen';
import { AdminNavigator } from '@/admin/AdminNavigator';
import { CatalogZonesScreen } from '@/admin/catalogue/CatalogZonesScreen';
import { CatalogZoneTradesScreen } from '@/admin/catalogue/CatalogZoneTradesScreen';
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

  const space = resolveSpace({
    isLoading: isLoading || spaceLoading,
    isAuthenticated,
    user,
    onboardingComplete,
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
        <SpaceSwitcherScreen onChoose={(next) => void choose(next)} />
      </View>
    );
  }

  if (space === 'admin') {
    return (
      <View testID="root-navigator" style={{ flex: 1 }}>
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          <Stack.Screen name="AdminSpace" component={AdminNavigator} />
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
            name="AdminCatalogTrades"
            component={CatalogZoneTradesScreen}
            options={({ route }) => ({
              headerShown: true,
              title: (route.params as { title?: string } | undefined)?.title ?? 'Catalogue',
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
        ) : isAuthenticated ? (
          <>
            <Stack.Screen name="MainTabs" component={TabNavigator} />
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
