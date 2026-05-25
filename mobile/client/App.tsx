import './src/sentry/init';
import { setupForegroundNotifications } from '@/push';
setupForegroundNotifications();
import React from 'react';
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
import { useRegisterPushToken } from '@/push';
import { env } from '@/config/env';
import { ErrorBoundary } from '@/ErrorBoundary';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

function AppInner() {
  useRegisterPushToken();
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
