import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '../api/client';
import { estUneLangue, type Langue } from './types';

const CLE_STOCKAGE = 'brio_langue';

let langueCourante: Langue = 'fr';
const ecouteurs = new Set<() => void>();
let charge = false;

export function langueActuelle(): Langue {
  return langueCourante;
}

export function ecouterLaLangue(rappel: () => void): () => void {
  ecouteurs.add(rappel);

  return () => {
    ecouteurs.delete(rappel);
  };
}

/**
 * Relit la langue choisie au démarrage : sans cela le choix ne survit pas à la fermeture,
 * `langueCourante` n'étant qu'une variable de module. Même piège que le mode sombre.
 */
export async function chargerLaLangueEnregistree(): Promise<void> {
  if (charge) {
    return;
  }
  charge = true;

  try {
    const enregistree = await AsyncStorage.getItem(CLE_STOCKAGE);
    if (estUneLangue(enregistree)) {
      langueCourante = enregistree;
      ecouteurs.forEach(cb => cb());
    }
  } catch {
    // Stockage indisponible : on reste en français.
  }
}

/**
 * La langue du compte, telle que le serveur la connaît.
 *
 * Elle est normalisée : la colonne vaut `fr_BE` pour la plupart des comptes, et l'écran de langue
 * n'en propose que la partie primaire.
 */
export function adopterLaLangueDuCompte(locale: string | null | undefined): void {
  const primaire = (locale ?? '').toLowerCase().replace('-', '_').split('_')[0];

  if (estUneLangue(primaire) && primaire !== langueCourante) {
    langueCourante = primaire;
    ecouteurs.forEach(cb => cb());
    void AsyncStorage.setItem(CLE_STOCKAGE, primaire).catch(() => undefined);
  }
}

/** Le choix de l'utilisateur : appliqué tout de suite, conservé, puis annoncé au serveur. */
export async function choisirLaLangue(langue: Langue, chemin = '/client/profile'): Promise<void> {
  langueCourante = langue;
  ecouteurs.forEach(cb => cb());

  try {
    await AsyncStorage.setItem(CLE_STOCKAGE, langue);
  } catch {
    // Stockage refusé : le choix vaut pour cette session.
  }

  await apiClient.put(chemin, { locale: langue });
}
