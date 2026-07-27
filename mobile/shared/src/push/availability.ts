import { Platform } from 'react-native';
import { isRunningInExpoGo } from 'expo';

let didWarn = false;

/**
 * Can `expo-notifications` be imported at all in the current runtime?
 *
 * On Android inside Expo Go (SDK 53+) the answer is NO — and not just "the API
 * fails": the module throws while it is being *evaluated*. `expo-notifications`
 * re-exports `DevicePushTokenAutoRegistration.fx`, whose module top level calls
 * `addPushTokenListener()` → `warnOfExpoGoPushUsage()`, which throws on Android
 * when `isRunningInExpoGo()` is true.
 *
 * That is why a dynamic `import()` + try/catch is not enough: it keeps the app
 * alive but the async chunk loader still reports the throw to the console
 * ("[Error: expo-notifications: Android Push notifications … removed from Expo
 * Go with the release of SDK 53 …]"). The only clean fix is to never touch the
 * module there.
 *
 * iOS in Expo Go only warns (see `warnOfExpoGoPushUsage`), so it stays usable.
 * Real push on Android needs a development build (`expo-dev-client` is already
 * a dependency of both apps): `npx expo run:android` or an EAS dev build.
 */
export function isPushModuleAvailable(): boolean {
  if (Platform.OS !== 'android') return true;

  let inExpoGo = false;
  try {
    inExpoGo = typeof isRunningInExpoGo === 'function' ? isRunningInExpoGo() : false;
  } catch {
    // If we cannot tell, assume a real build rather than silently killing push.
    return true;
  }

  if (inExpoGo && __DEV__ && !didWarn) {
    didWarn = true;
    console.info(
      '[push] Notifications désactivées : Expo Go Android ne supporte plus expo-notifications (SDK 53+). ' +
        'Utilise un development build (`npx expo run:android`) pour tester le push.',
    );
  }

  return !inExpoGo;
}
