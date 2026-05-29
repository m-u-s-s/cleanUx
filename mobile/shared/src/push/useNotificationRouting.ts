import { useEffect } from 'react';
import { useNavigation } from '@react-navigation/native';

/**
 * Wires up deep navigation from push notification taps.
 * Expects notification data to carry { screen, params? }.
 * Uses a dynamic import for expo-notifications to avoid crashes in Expo Go SDK 53+.
 */
export function useNotificationRouting(): void {
  // Use the any-typed navigation to avoid coupling shared to a specific param list
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const navigation = useNavigation<any>();

  useEffect(() => {
    let sub: { remove: () => void } | null = null;

    (async () => {
      try {
        const Notifications = await import('expo-notifications');
        sub = Notifications.addNotificationResponseReceivedListener(response => {
          const data = response.notification.request.content.data;
          if (data?.screen) {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            navigation.navigate(data.screen as string, (data.params ?? {}) as any);
          }
        });
      } catch {
        // expo-notifications not available in Expo Go SDK 53+
      }
    })();

    return () => { sub?.remove(); };
  }, [navigation]);
}
