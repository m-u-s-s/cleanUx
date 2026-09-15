import { Ionicons } from '@expo/vector-icons';

export type NomIonicons = keyof typeof Ionicons.glyphMap;

/**
 * Les icônes du catalogue sont nommées par le web (Heroicons). Le mobile dessine en Ionicons :
 * chaque cible est vérifiée contre le vrai fichier de glyphes par `iconeDuMetier.test.ts`.
 */
export const ICONES_DU_CATALOGUE: Readonly<Record<string, NomIonicons>> = {
  hammer: 'hammer-outline',
  sparkles: 'sparkles-outline',
  tree: 'leaf-outline',
  leaf: 'leaf-outline',
  users: 'people-outline',
  'user-group': 'people-outline',
  'shield-check': 'shield-checkmark-outline',
  car: 'car-outline',
  'paint-roller': 'brush-outline',
  'paint-brush': 'brush-outline',
  broom: 'trash-bin-outline',
  wrench: 'build-outline',
  bolt: 'flash-outline',
  window: 'grid-outline',
  home: 'home-outline',
  truck: 'cube-outline',
  'arrow-up': 'arrow-up-outline',
  'pencil-square': 'create-outline',
};

/** Le repli du modèle `Trade` est `briefcase` : on garde la même idée. */
export const ICONE_PAR_DEFAUT: NomIonicons = 'briefcase-outline';

export function iconeDuMetier(nom: string | null | undefined): NomIonicons {
  // `hasOwnProperty` : un nom comme « constructor » atteindrait sinon le prototype de l'objet.
  if (nom && Object.prototype.hasOwnProperty.call(ICONES_DU_CATALOGUE, nom)) {
    return ICONES_DU_CATALOGUE[nom] as NomIonicons;
  }

  return ICONE_PAR_DEFAUT;
}
