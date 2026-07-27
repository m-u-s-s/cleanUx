/**
 * Regression — Expo Go SDK 53+ / Android.
 *
 * `expo-notifications` throws at *module-evaluation* time on Android inside
 * Expo Go: `build/index.js` re-exports `DevicePushTokenAutoRegistration.fx`,
 * whose module top level calls `addPushTokenListener()` → `warnOfExpoGoPushUsage()`
 * → `throw new Error('… removed from Expo Go with the release of SDK 53 …')`.
 *
 * A try/catch around the import swallows the throw, but the async chunk loader
 * still reports it (`metro:client_log level=error`), so the only clean fix is to
 * never import the module in that environment.
 */
jest.mock('expo', () => ({ isRunningInExpoGo: () => true }), { virtual: true });
jest.mock(
  'expo-notifications',
  () => ({
    setNotificationHandler: jest.fn(),
    AndroidNotificationPriority: { HIGH: 'HIGH' },
  }),
  { virtual: true },
);

import { Platform } from 'react-native';
import { isPushModuleAvailable } from '@/push/availability';
import { setupForegroundNotifications } from '@/push/foreground';

function forcePlatform(os: string) {
  Object.defineProperty(Platform, 'OS', { value: os, configurable: true });
}

describe('Expo Go push guard (Android, SDK 53+)', () => {
  const originalOS = Platform.OS;

  afterEach(() => {
    forcePlatform(originalOS);
    jest.clearAllMocks();
  });

  it('reports the push module as unavailable on Android in Expo Go', () => {
    forcePlatform('android');
    expect(isPushModuleAvailable()).toBe(false);
  });

  it('still reports it available on iOS in Expo Go (only Android push was removed)', () => {
    forcePlatform('ios');
    expect(isPushModuleAvailable()).toBe(true);
  });

  it('never imports expo-notifications on Android in Expo Go', () => {
    forcePlatform('android');
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const Notifications = require('expo-notifications');
    setupForegroundNotifications();
    expect(Notifications.setNotificationHandler).not.toHaveBeenCalled();
  });
});
