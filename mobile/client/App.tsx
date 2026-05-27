import '@/sentry/init';
import { setupForegroundNotifications } from '@/push';
setupForegroundNotifications();
import React, { useState, useEffect } from 'react';
import { View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StripeProvider } from '@stripe/stripe-react-native';
import {
  useFonts,
  Figtree_400Regular,
  Figtree_500Medium,
  Figtree_600SemiBold,
  Figtree_700Bold,
} from '@expo-google-fonts/figtree';
import { SpaceGrotesk_700Bold } from '@expo-google-fonts/space-grotesk';
import { AuthProvider } from '@/auth';
import { RealtimeProvider } from '@/realtime';
import { RootNavigator, linking } from '@/navigation';
import { useRegisterPushToken, useNotificationRouting } from '@/push';
import { env } from '@/config/env';
import { ErrorBoundary } from '@/ErrorBoundary';
import { OnboardingScreen, hasCompletedOnboarding } from '@/screens/OnboardingScreen';
import { useOfflineSync } from '@/api';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

function AppInner() {
  useRegisterPushToken();
  useNotificationRouting();
  useOfflineSync();
  const [showOnboarding, setShowOnboarding] = useState<boolean | null>(null);

  useEffect(() => {
    hasCompletedOnboarding().then(completed => setShowOnboarding(!completed));
  }, []);

  if (showOnboarding === null) {
    return <View style={{ flex: 1 }} />;
  }

  if (showOnboarding) {
    return (
      <SafeAreaProvider>
        <OnboardingScreen onComplete={() => setShowOnboarding(false)} />
        <StatusBar style="light" />
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider>
      <NavigationContainer linking={linking}>
        <RootNavigator />
      </NavigationContainer>
      <StatusBar style="auto" />
    </SafeAreaProvider>
  );
}

export default function App() {
  const [fontsLoaded] = useFonts({
    Figtree_400Regular,
    Figtree_500Medium,
    Figtree_600SemiBold,
    Figtree_700Bold,
    SpaceGrotesk_700Bold,
  });

  if (!fontsLoaded) {
    return <View />;
  }

  return (
    <ErrorBoundary>
      <StripeProvider publishableKey={env.stripePublishableKey} merchantIdentifier="merchant.com.cleanux.client">
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <RealtimeProvider>
              <AppInner />
            </RealtimeProvider>
          </AuthProvider>
        </QueryClientProvider>
      </StripeProvider>
    </ErrorBoundary>
  );
}
