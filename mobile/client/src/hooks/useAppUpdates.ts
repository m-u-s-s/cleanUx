import { useEffect } from 'react';

/**
 * Checks for an available OTA update on mount (production builds only).
 * Uses a dynamic import so the module is not resolved in Expo Go SDK 53+,
 * which crashes on static expo-updates imports when updates are not configured.
 *
 * Silent-fail: any error is swallowed to never block the user.
 */
export function useAppUpdates(): void {
  useEffect(() => {
    if (__DEV__) return;

    (async () => {
      try {
        const Updates = await import('expo-updates');
        const update = await Updates.checkForUpdateAsync();
        if (update.isAvailable) {
          await Updates.fetchUpdateAsync();
          await Updates.reloadAsync();
        }
      } catch {
        // Silent fail — do not block app usage on update errors
      }
    })();
  }, []);
}
