/*
 * L'application se DÉCLARE avant tout le reste.
 *
 * Le serveur refuse un compte prestataire dans l'application cliente et l'inverse ; il ne
 * peut le faire que s'il sait à qui il parle. En l'absence de déclaration il laisse passer,
 * pour ne pas déconnecter le parc déjà installé — un oubli ici serait donc SILENCIEUX.
 *
 * Avant les fournisseurs : la reprise de session part dès le montage d'`AuthProvider`, et
 * elle doit déjà porter l'en-tête.
 */
import { setAppAudience } from '@/api';
setAppAudience('provider');

import { setupForegroundNotifications, useNotificationRouting } from '@/push';
setupForegroundNotifications();
import React, { useState, useEffect } from 'react';
import { View } from 'react-native';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { NightShell, useThemeDeNavigation } from '@/ui/NightShell';
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
import { useOfflineSync, bindAppStateToQueryFocus } from '@/api';

// Relie React Query au cycle de vie de l'application : sans ce pont, aucune requête n'est
// rejouée au retour au premier plan et l'écran reste figé sur des données périmées.
bindAppStateToQueryFocus();

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

/**
 * Hooks that call useNavigation() must run *inside* <NavigationContainer>.
 * AppInner is the component that renders the container, so anything it calls
 * directly executes outside of it — useNotificationRouting() there threw
 * "Couldn't find a navigation object. Is your component inside
 * NavigationContainer?" and took the whole app down with a render error.
 */
function NavigationEffects(): null {
  useNotificationRouting();
  return null;
}

function AppInner() {
  useOfflineSync();
  const themeNavigation = useThemeDeNavigation();
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
      <NightShell>
        <NavigationContainer linking={linking} theme={themeNavigation}>
          <NavigationEffects />
          <RootNavigator />
        </NavigationContainer>
      </NightShell>
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
    <GestureHandlerRootView style={{ flex: 1 }}>
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <RealtimeProvider>
              <AppInner />
            </RealtimeProvider>
          </AuthProvider>
        </QueryClientProvider>
      </ErrorBoundary>
    </GestureHandlerRootView>
  );
}
