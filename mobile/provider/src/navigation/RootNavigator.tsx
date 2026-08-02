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
import { TabNavigator } from './TabNavigator';
import { AsapOffersScreen } from '@/asap';
import { colors } from '@/theme';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const { isAuthenticated, isLoading } = useAuth();

  // Le parcours de vérification garde l'entrée de l'application. Sans lui, un compte tout juste
  // créé atterrissait sur le tableau de bord où chaque appel échouait en 403, sans explication.
  // La requête n'est lancée qu'une fois authentifié — l'endpoint l'exige.
  const { data: onboarding, isLoading: onboardingLoading, isError: onboardingError } =
    useOnboardingProgress(isAuthenticated);

  if (isLoading) {
    return (
      <View
        testID="root-navigator"
        style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.surface[50] }}
      >
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  return (
    <View testID="root-navigator" style={{ flex: 1 }}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {isAuthenticated && !onboardingLoading && !onboardingError && !isJourneyComplete(onboarding) ? (
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
