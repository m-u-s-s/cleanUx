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
          </>
        ) : (
          <Stack.Screen name="Login" component={LoginScreen} />
        )}
      </Stack.Navigator>
    </View>
  );
}
