import { useEffect } from 'react';
import { AppState, Alert } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { offlineQueue } from '../lib/offlineQueue';

async function flushIfOnline(): Promise<void> {
  const netState = await NetInfo.fetch();

  if (!netState.isConnected) {
    return;
  }

  const result = await offlineQueue.flush();

  if (result.success > 0) {
    console.log(`[offlineSync] Synced ${result.success} queued action(s)`);
  }

  /*
   * CE QUI A ÉTÉ ABANDONNÉ DOIT SE DIRE.
   *
   * Un geste refusé définitivement par le serveur sort de la file — sans quoi il repartirait à
   * chaque reconnexion, pour toujours. Mais le sortir en silence est pire : la personne croit
   * avoir coché sa tâche, et personne ne la détrompera jamais. On nomme ce qui n'est pas passé,
   * et on l'oublie ensuite : le répéter à chaque reconnexion serait la même faute à l'envers.
   */
  if (result.dropped > 0) {
    const abandons = await offlineQueue.abandons();
    await offlineQueue.oublierLesAbandons();

    if (abandons.length > 0) {
      const lignes = abandons
        .map((a) => `• ${a.label ?? 'Une action'} — ${a.reason}`)
        .join(String.fromCharCode(10));

      Alert.alert('Certaines actions n’ont pas pu être enregistrées', lignes);
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
