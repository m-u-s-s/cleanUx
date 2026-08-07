import { creerPreferenceDEspace } from '@/storage/preferenceDEspace';
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
 * porte de sortie enfermerait dans l'autre sens.
 *
 * L'ÉTAT VIT DANS LE MODULE, PAS DANS LE HOOK, et c'est la correction du 2026-08-07 : écrit avec
 * `useState`, ce hook donnait à `RootNavigator` et à `ProfileScreen` deux états indépendants, si
 * bien que « Changer d'espace » ne faisait rien à l'écran. Voir `creerPreferenceDEspace`, qui porte
 * le raisonnement complet, et `preferenceDEspacePartagee.test.tsx`, qui garde le comportement.
 */
export const useClientSpacePreference = creerPreferenceDEspace<ChosenClientSpace>(STORAGE_KEY, [
  'personal',
  'clientCompany',
]);

export const CLIENT_SPACE_STORAGE_KEY = STORAGE_KEY;
