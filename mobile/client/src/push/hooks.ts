import { useEffect } from 'react';
import * as ExpoNotifications from 'expo-notifications';
import { apiClient } from '@/api';
import { useAuth } from '@/auth';
import { Platform } from 'react-native';

export function useRegisterPushToken() {
  const { isAuthenticated } = useAuth();

  useEffect(() => {
    if (!isAuthenticated) return;

    (async () => {
      try {
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
        // Silently ignore — push registration fails in Expo Go (SDK 53+)
        // and is best-effort in production builds
      }
    })();
  }, [isAuthenticated]);
}
