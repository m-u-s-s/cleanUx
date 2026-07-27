import { useEffect } from 'react';
import { apiClient } from '@/api';
import { useAuth } from '@/auth';
import { Platform } from 'react-native';
import { isPushModuleAvailable } from './availability';

export function useRegisterPushToken() {
  const { isAuthenticated } = useAuth();

  useEffect(() => {
    if (!isAuthenticated) return;
    // Android/Expo Go (SDK 53+): the import itself throws — see ./availability.
    if (!isPushModuleAvailable()) return;

    (async () => {
      try {
        const ExpoNotifications = await import('expo-notifications');
        const { status } = await ExpoNotifications.requestPermissionsAsync();
        if (status !== 'granted') return;

        const token = await ExpoNotifications.getExpoPushTokenAsync();
        if (!token?.data) return;

        await apiClient.post('/client/devices/register', {
          token: token.data,
          platform: Platform.OS,
          provider: 'expo',
        });
      } catch {
        // Silently ignore — expo-notifications crashes in Expo Go (SDK 53+)
        // Dynamic import ensures the crash is caught, not thrown at module load
      }
    })();
  }, [isAuthenticated]);
}
