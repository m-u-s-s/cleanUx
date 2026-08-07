import { useCallback, useEffect, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { ChosenSpace } from './space';

const STORAGE_KEY = 'brio_provider_space';

/**
 * L'espace choisi par un compte à double casquette, retenu d'un lancement à l'autre.
 *
 * POURQUOI RETENIR. Redemander à chaque démarrage ferait payer un écran de choix à quelqu'un qui
 * fait le même geste tous les matins. Le choix reste réversible depuis le profil : le retenir
 * sans porte de sortie enfermerait dans l'autre sens.
 *
 * POURQUOI PAS LE STOCKAGE SÉCURISÉ. Ce n'est pas un secret, c'est une préférence d'affichage.
 * L'autorité reste le serveur : un jeton prestataire ne devient pas administrateur parce qu'une
 * clé locale dit « admin ».
 *
 * `isLoading` existe pour que l'aiguillage n'ouvre pas l'espace par défaut le temps d'une lecture
 * asynchrone — la double casquette verrait sinon un écran clignoter avant le sien.
 */
export function useSpacePreference() {
  const [space, setSpace] = useState<ChosenSpace | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const stored = await AsyncStorage.getItem(STORAGE_KEY);
        if (!cancelled && (stored === 'admin' || stored === 'provider')) {
          setSpace(stored);
        }
      } catch {
        // Un stockage illisible n'a pas à empêcher d'entrer : on repose la question, c'est tout.
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const choose = useCallback(async (next: ChosenSpace) => {
    setSpace(next);
    try {
      await AsyncStorage.setItem(STORAGE_KEY, next);
    } catch {
      // L'écriture peut échouer sans conséquence : le choix vaut pour cette session.
    }
  }, []);

  const clear = useCallback(async () => {
    setSpace(undefined);
    try {
      await AsyncStorage.removeItem(STORAGE_KEY);
    } catch {
      // Idem — l'état local a déjà repris la main.
    }
  }, []);

  return { space, isLoading, choose, clear };
}

export const SPACE_STORAGE_KEY = STORAGE_KEY;
