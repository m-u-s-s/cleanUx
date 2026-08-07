import { creerPreferenceDEspace } from '@/storage/preferenceDEspace';
import type { ChosenSpace } from './space';

/**
 * Clé PROPRE à l'application prestataire.
 *
 * L'application cliente retient la sienne sous `brio_client_space`. Partager une clé entre deux
 * APK installés côte à côte ferait qu'un choix fait ici déciderait aussi de l'espace ouvert
 * là-bas — deux réglages différents portant le même nom.
 */
const STORAGE_KEY = 'brio_provider_space';

/**
 * L'espace choisi par un compte à plusieurs casquettes, retenu d'un lancement à l'autre.
 *
 * POURQUOI RETENIR. Redemander à chaque démarrage ferait payer un écran de choix à quelqu'un qui
 * fait le même geste tous les matins. Le choix reste réversible depuis le profil : le retenir sans
 * porte de sortie enfermerait dans l'autre sens.
 *
 * L'ÉTAT VIT DANS LE MODULE, PAS DANS LE HOOK, et c'est la correction du 2026-08-07 : écrit avec
 * `useState`, ce hook donnait à `RootNavigator` et aux trois écrans de profil des états
 * indépendants, si bien que « Changer d'espace » et « Aller à l'espace terrain » ne faisaient rien
 * à l'écran. Voir `creerPreferenceDEspace`, qui porte le raisonnement complet, et
 * `preferenceDEspacePartagee.test.tsx`, qui garde le comportement.
 */
/*
 * `superAdmin` figure dans la liste, sans quoi un choix écrit serait relu comme inconnu au
 * lancement suivant et reposerait la question — la liste est explicite précisément pour qu'une
 * valeur non prévue par cette version n'ouvre pas un espace qui n'existe pas.
 */
export const useSpacePreference = creerPreferenceDEspace<ChosenSpace>(STORAGE_KEY, [
  'superAdmin',
  'admin',
  'provider',
  'providerCompany',
]);

export const SPACE_STORAGE_KEY = STORAGE_KEY;
