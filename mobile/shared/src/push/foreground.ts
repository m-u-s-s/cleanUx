import { Platform } from 'react-native';
import { isPushModuleAvailable } from './availability';

export function setupForegroundNotifications() {
  // Importing expo-notifications throws on Android/Expo Go (SDK 53+) — see
  // ./availability. Bail out before the require, a try/catch is not enough.
  if (!isPushModuleAvailable()) return;

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
