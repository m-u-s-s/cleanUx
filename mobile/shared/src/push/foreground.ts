import { Platform } from 'react-native';

export function setupForegroundNotifications() {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const Notifications = require('expo-notifications');
    Notifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowAlert: true,
        shouldPlaySound: true,
        shouldSetBadge: true,
        priority:
          Platform.OS === 'android'
            ? Notifications.AndroidNotificationPriority?.HIGH
            : undefined,
      }),
    });
  } catch {
    // expo-notifications not available (Expo Go SDK 53+)
  }
}
