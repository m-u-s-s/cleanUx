import { useCallback, useEffect, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import type { ChosenClientSpace } from './space';

/**
 * Clé PROPRE à l'application cliente.
 *
 * L'application prestataire retient la sienne sous `cleanux_provider_space`. Partager une clé
 * entre deux APK installés côte à côte ferait qu'un choix « société » fait ici déciderait aussi de
 * l'espace ouvert là-bas — deux réglages différents portant le même nom.
 */
const STORAGE_KEY = 'cleanux_client_space';

/**
 * L'espace choisi par un compte à la fois particulier et membre d'une société, retenu d'un
 * lancement à l'autre.
 *
 * POURQUOI RETENIR. Redemander à chaque démarrage ferait payer un écran de choix à quelqu'un qui
 * fait le même geste tous les matins. Le choix reste réversible depuis le profil : le retenir sans
 * porte de sortie enfermerait dans l'autre sens — le défaut que `clear()` a déjà corrigé deux fois
 * dans ce dépôt.
 *
 * POURQUOI PAS LE STOCKAGE SÉCURISÉ. Ce n'est pas un secret, c'est une préférence d'affichage.
 * L'autorité reste le serveur : une clé locale qui dit « société » ne donne accès à rien, chaque
 * écran étant gardé par `/api/client/company/*`.
 *
 * `isLoading` existe pour que l'aiguillage n'ouvre pas l'espace par défaut le temps d'une lecture
 * asynchrone : le compte à double vie verrait sinon clignoter un écran qui n'est pas le sien.
 */
export function useClientSpacePreference() {
  const [space, setSpace] = useState<ChosenClientSpace | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const stored = await AsyncStorage.getItem(STORAGE_KEY);
        // Liste explicite plutôt qu'un transtypage : une valeur inconnue — clé écrite par une
        // version future, stockage corrompu — doit reposer la question, pas ouvrir un espace qui
        // n'existe pas dans cette version.
        if (!cancelled && (stored === 'personal' || stored === 'clientCompany')) {
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

  const choose = useCallback(async (next: ChosenClientSpace) => {
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

export const CLIENT_SPACE_STORAGE_KEY = STORAGE_KEY;
