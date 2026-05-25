import React from 'react';
import { View, ActivityIndicator } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { LoginScreen } from '@/screens/LoginScreen';
import { TabNavigator } from './TabNavigator';
import { BookingNavigator } from './BookingNavigator';
import { MissionTrackingScreen } from '@/screens/MissionTrackingScreen';
import { BookingDetailScreen } from '@/screens/BookingDetailScreen';
import { QRScanScreen } from '@/screens/QRScanScreen';
import { PaymentCheckoutScreen } from '@/screens/PaymentCheckoutScreen';
import { SavedPaymentMethodsScreen } from '@/screens/SavedPaymentMethodsScreen';
import { ChatScreen } from '@/screens/ChatScreen';
import { ChatListScreen } from '@/screens/ChatListScreen';
import { NotificationsScreen } from '@/screens/NotificationsScreen';
// Sprint 9
import { RatingScreen } from '@/screens/RatingScreen';
import { LoyaltyScreen } from '@/screens/LoyaltyScreen';
import { ReferralScreen } from '@/screens/ReferralScreen';
import { AiQuoteScreen } from '@/screens/AiQuoteScreen';
// Sprint 10
import { DisputesScreen } from '@/screens/DisputesScreen';
import { GDPRScreen } from '@/screens/GDPRScreen';
import { ProfileEditScreen } from '@/screens/ProfileEditScreen';
import { TipsScreen } from '@/screens/TipsScreen';
import { NPSScreen } from '@/screens/NPSScreen';
import { ForgotPasswordScreen } from '@/screens/ForgotPasswordScreen';
import { colors } from '@/theme';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <View testID="root-navigator" style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.surface[50] }}>
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
              name="BookingWizard"
              component={BookingNavigator}
              options={{ headerShown: false, presentation: 'modal' }}
            />
            <Stack.Screen
              name="MissionTracking"
              component={MissionTrackingScreen}
              options={{ title: 'Suivi mission' }}
            />
            <Stack.Screen
              name="BookingDetail"
              component={BookingDetailScreen}
              options={{ title: 'Détail réservation' }}
            />
            <Stack.Screen
              name="QRScan"
              component={QRScanScreen}
              options={{ headerShown: false, presentation: 'fullScreenModal' }}
            />
            <Stack.Screen
              name="PaymentCheckout"
              component={PaymentCheckoutScreen}
              options={{ title: 'Paiement', headerShown: true }}
            />
            <Stack.Screen
              name="SavedPaymentMethods"
              component={SavedPaymentMethodsScreen}
              options={{ title: 'Moyens de paiement', headerShown: true }}
            />
            <Stack.Screen
              name="ChatList"
              component={ChatListScreen}
              options={{ title: 'Messagerie', headerShown: true }}
            />
            <Stack.Screen
              name="Chat"
              component={ChatScreen}
              options={({ route }) => ({ title: route.params.title, headerShown: true })}
            />
            <Stack.Screen
              name="Notifications"
              component={NotificationsScreen}
              options={{ title: 'Notifications', headerShown: true }}
            />
            {/* Sprint 9 */}
            <Stack.Screen
              name="Rating"
              component={RatingScreen}
              options={{ title: 'Évaluer', headerShown: true }}
            />
            <Stack.Screen
              name="Loyalty"
              component={LoyaltyScreen}
              options={{ title: 'Fidélité', headerShown: true }}
            />
            <Stack.Screen
              name="Referral"
              component={ReferralScreen}
              options={{ title: 'Parrainage', headerShown: true }}
            />
            <Stack.Screen
              name="AiQuote"
              component={AiQuoteScreen}
              options={{ title: 'Devis IA', headerShown: true }}
            />
            {/* Sprint 10 */}
            <Stack.Screen
              name="Disputes"
              component={DisputesScreen}
              options={{ title: 'Litiges', headerShown: true }}
            />
            <Stack.Screen
              name="GDPR"
              component={GDPRScreen}
              options={{ title: 'Mes données', headerShown: true }}
            />
            <Stack.Screen
              name="ProfileEdit"
              component={ProfileEditScreen}
              options={{ title: 'Modifier le profil', headerShown: true }}
            />
            <Stack.Screen
              name="Tips"
              component={TipsScreen}
              options={{ title: 'Pourboire', headerShown: true }}
            />
            <Stack.Screen
              name="NPS"
              component={NPSScreen}
              options={{ title: 'Votre avis', headerShown: true }}
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
