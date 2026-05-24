import './src/sentry/init';
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '@/auth';
import { RootNavigator, linking } from '@/navigation';

const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 2, staleTime: 60_000 } },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <SafeAreaProvider>
          <NavigationContainer linking={linking}>
            <RootNavigator />
          </NavigationContainer>
          <StatusBar style="auto" />
        </SafeAreaProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
