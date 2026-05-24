import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '@/auth';
import { RealtimeProvider } from '@/realtime';
import { RootNavigator, linking } from '@/navigation';
import { ErrorBoundary } from '@/ErrorBoundary';
import './src/sentry/init';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

export default function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <RealtimeProvider>
            <SafeAreaProvider>
              <NavigationContainer linking={linking}>
                <RootNavigator />
              </NavigationContainer>
              <StatusBar style="auto" />
            </SafeAreaProvider>
          </RealtimeProvider>
        </AuthProvider>
      </QueryClientProvider>
    </ErrorBoundary>
  );
}
