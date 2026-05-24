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
      const { status } = await ExpoNotifications.requestPermissionsAsync();
      if (status !== 'granted') return;

      const token = await ExpoNotifications.getExpoPushTokenAsync();
      if (!token?.data) return;

      try {
        await apiClient.post('/client/devices/register', {
          token: token.data,
          platform: Platform.OS,
          provider: 'expo',
        });
      } catch {
        // silently ignore — device registration is best-effort
      }
    })();
  }, [isAuthenticated]);
}
