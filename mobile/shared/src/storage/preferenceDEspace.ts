import { useCallback, useEffect, useSyncExternalStore } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

/**
 * LE CHOIX D'ESPACE, EN SOURCE DE VÉRITÉ UNIQUE.
 *
 * POURQUOI CETTE FABRIQUE EXISTE. Les deux applications retenaient l'espace choisi dans un hook à
 * `useState` local — `useClientSpacePreference` ici, `useSpacePreference` là-bas. Chaque appelant
 * obtenait donc son PROPRE état :
 *
 *   - `RootNavigator` en détenait un — celui qui décide de l'espace rendu ;
 *   - les écrans de profil en détenaient d'autres.
 *
 * Presser « Changer d'espace » depuis un profil mettait à jour l'état DE CET ÉCRAN et écrivait
 * dans AsyncStorage. L'instance du navigateur, dont le `useEffect` a des dépendances `[]`, avait lu
 * la clé une fois au montage et ne la relisait jamais : elle n'apprenait rien. À l'écran, rien ne
 * se passait — jusqu'au prochain lancement de l'application.
 *
 * Les SÉLECTEURS d'espace fonctionnaient, et c'est ce qui a masqué le défaut : `RootNavigator` leur
 * passe son PROPRE `choose` en prop, donc la même instance.
 *
 * POURQUOI UNE FABRIQUE PARTAGÉE ET NON DEUX COPIES. Les deux hooks étaient déjà deux copies du
 * même code, et ils portaient donc deux fois le même défaut. Ce qui diffère entre les applications
 * — la clé de stockage et les valeurs admises — se passe en paramètre ; le reste est identique et
 * n'a aucune raison d'être réécrit.
 *
 * POURQUOI `useSyncExternalStore` ET NON UN CONTEXTE REACT. Le hook s'appelle depuis
 * `RootNavigator`, qui est précisément ce qu'un fournisseur devrait envelopper : il aurait fallu le
 * hisser au-dessus dans les `App.tsx` des deux applications, avec un ordre de montage à tenir. Un
 * store de module n'impose rien à l'arbre et reste juste où qu'on appelle le hook.
 *
 * POURQUOI PAS LE STOCKAGE SÉCURISÉ. Ce n'est pas un secret, c'est une préférence d'affichage.
 * L'autorité reste le serveur : une clé locale qui dit « société » ne donne accès à rien, chaque
 * écran restant gardé côté API.
 */
export interface PreferenceDEspace<T extends string> {
  /** L'espace retenu, ou `undefined` tant qu'aucun choix n'a été fait. */
  space: T | undefined;
  /**
   * Vrai tant que la lecture initiale n'a pas rendu. L'aiguillage doit l'attendre : ouvrir
   * l'espace par défaut le temps d'une lecture asynchrone ferait clignoter un écran qui n'est pas
   * celui de l'utilisateur.
   */
  isLoading: boolean;
  choose: (next: T) => Promise<void>;
  clear: () => Promise<void>;
}

/**
 * @param cle          Clé AsyncStorage, PROPRE à chaque application. Partager une clé entre deux
 *                     APK installés côte à côte ferait qu'un choix fait ici déciderait aussi de
 *                     l'espace ouvert là-bas — deux réglages différents portant le même nom.
 * @param valeursAdmises Liste explicite plutôt qu'un transtypage : une valeur inconnue — clé
 *                     écrite par une version future, stockage corrompu — doit reposer la question,
 *                     pas ouvrir un espace qui n'existe pas dans cette version.
 */
export function creerPreferenceDEspace<T extends string>(
  cle: string,
  valeursAdmises: readonly T[],
): () => PreferenceDEspace<T> {
  interface Etat {
    space: T | undefined;
    isLoading: boolean;
  }

  let etat: Etat = { space: undefined, isLoading: true };

  const abonnes = new Set<() => void>();

  /** La lecture initiale n'a lieu qu'UNE fois pour toute l'application, pas une par appelant. */
  let lectureInitiale: Promise<void> | null = null;

  function publier(suivant: Etat) {
    etat = suivant;
    abonnes.forEach((notifier) => notifier());
  }

  function sabonner(notifier: () => void) {
    abonnes.add(notifier);

    return () => {
      abonnes.delete(notifier);
    };
  }

  // L'identité de `etat` ne change qu'à la publication : `useSyncExternalStore` exige un instantané
  // stable, sans quoi il redemande un rendu à chaque appel.
  function lireLInstantane(): Etat {
    return etat;
  }

  function estAdmise(valeur: string | null): valeur is T {
    return valeur !== null && (valeursAdmises as readonly string[]).includes(valeur);
  }

  async function hydrater() {
    try {
      const stocke = await AsyncStorage.getItem(cle);

      publier({ space: estAdmise(stocke) ? stocke : undefined, isLoading: false });
    } catch {
      // Un stockage illisible n'a pas à empêcher d'entrer : on repose la question, c'est tout.
      publier({ space: undefined, isLoading: false });
    }
  }

  return function usePreferenceDEspace(): PreferenceDEspace<T> {
    const { space, isLoading } = useSyncExternalStore(sabonner, lireLInstantane);

    useEffect(() => {
      lectureInitiale ??= hydrater();
    }, []);

    const choose = useCallback(async (next: T) => {
      // On publie AVANT d'écrire : l'affichage suit le geste immédiatement, et une écriture qui
      // échoue ne doit pas retenir l'utilisateur sur un écran qu'il vient de quitter.
      publier({ space: next, isLoading: false });

      try {
        await AsyncStorage.setItem(cle, next);
      } catch {
        // L'écriture peut échouer sans conséquence : le choix vaut pour cette session.
      }
    }, []);

    const clear = useCallback(async () => {
      publier({ space: undefined, isLoading: false });

      try {
        await AsyncStorage.removeItem(cle);
      } catch {
        // Idem — l'état publié a déjà repris la main.
      }
    }, []);

    return { space, isLoading, choose, clear };
  };
}
