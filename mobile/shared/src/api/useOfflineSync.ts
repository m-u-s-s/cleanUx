import { useEffect } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { offlineQueue } from '../lib/offlineQueue';

async function flushIfOnline(): Promise<void> {
  const netState = await NetInfo.fetch();
  if (netState.isConnected) {
    const result = await offlineQueue.flush();
    if (result.success > 0) {
      console.log(`[offlineSync] Synced ${result.success} queued action(s)`);
    }
  }
}

/**
 * Mount this hook once at the app root to automatically replay queued
 * mutations whenever connectivity is restored or the app returns to
 * the foreground.
 */
export function useOfflineSync(): void {
  useEffect(() => {
    const unsubscribeNet = NetInfo.addEventListener((state) => {
      if (state.isConnected) {
        void flushIfOnline();
      }
    });

    const appStateSub = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        void flushIfOnline();
      }
    });

    return () => {
      unsubscribeNet();
      appStateSub.remove();
    };
  }, []);
}
