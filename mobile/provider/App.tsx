import { setupForegroundNotifications, useNotificationRouting } from '@/push';
setupForegroundNotifications();
import React, { useState, useEffect } from 'react';
import { View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
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
import { ErrorBoundary } from '@/ErrorBoundary';
import { WalkthroughScreen, hasCompletedWalkthrough } from '@/screens/WalkthroughScreen';
import '@/sentry/init';
import { useOfflineSync } from '@/api';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

function AppInner() {
  useNotificationRouting();
  useOfflineSync();
  const [showWalkthrough, setShowWalkthrough] = useState<boolean | null>(null);

  useEffect(() => {
    hasCompletedWalkthrough().then(completed => setShowWalkthrough(!completed));
  }, []);

  if (showWalkthrough === null) {
    return <View style={{ flex: 1 }} />;
  }

  if (showWalkthrough) {
    return (
      <SafeAreaProvider>
        <WalkthroughScreen onComplete={() => setShowWalkthrough(false)} />
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
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <RealtimeProvider>
            <AppInner />
          </RealtimeProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
}
