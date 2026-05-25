import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { LoginScreen } from '@/screens/LoginScreen';
import { MissionDetailScreen } from '@/screens/MissionDetailScreen';
import { MissionInboxScreen } from '@/screens/MissionInboxScreen';
import { MissionFieldScreen } from '@/screens/MissionFieldScreen';
import { StripeOnboardingScreen } from '@/screens/StripeOnboardingScreen';
import { AvailabilityScreen } from '@/screens/AvailabilityScreen';
import { BadgesScreen } from '@/screens/BadgesScreen';
import { KYCScreen } from '@/screens/KYCScreen';
import { ProviderDisputesScreen } from '@/screens/ProviderDisputesScreen';
import { ProviderRatingsScreen } from '@/screens/ProviderRatingsScreen';
import { OnboardingScreen } from '@/screens/OnboardingScreen';
import { ProviderChatListScreen } from '@/screens/ProviderChatListScreen';
import { ProviderChatScreen } from '@/screens/ProviderChatScreen';
import { ProviderNotificationsScreen } from '@/screens/ProviderNotificationsScreen';
import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { TabNavigator } from './TabNavigator';
import { colors } from '@/theme';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const { isAuthenticated, isLoading } = useAuth();

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
        {isAuthenticated ? (
          <>
            <Stack.Screen name="MainTabs" component={TabNavigator} />
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
              name="Onboarding"
              component={OnboardingScreen}
              options={{ headerShown: true, title: 'Onboarding' }}
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
          </>
        ) : (
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} options={{ title: 'Mot de passe oublié', headerShown: true }} />
          </>
        )}
      </Stack.Navigator>
    </View>
  );
}
