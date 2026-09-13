import React from 'react';
import { StyleSheet, type ViewStyle } from 'react-native';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { GlassSurface } from './GlassSurface';

/*
 * `apparenceDeBarre` A ETE RETIREE D'ICI.
 *
 * Elle habillait la barre par defaut de React Navigation d'une plaque de verre, pour les espaces
 * societe et la console d'administration. Ces trois navigateurs montent desormais la meme barre
 * « Remontee » que les deux accueils : deux habillages differents se voyaient des qu'on passait
 * d'un espace a l'autre, et le second n'avait plus de raison d'exister.
 */

/**
 * L'apparence d'un EN-TÊTE, pour la même raison que la barre d'onglets.
 *
 * `@react-navigation/native-stack` rend un en-tête NATIF : il ne lit pas `colors.card` du thème de
 * navigation comme le fait la pile JavaScript, et retombe sur la surface par défaut d'Android —
 * un aplat blanc en clair, un gris-bleu en sombre. Résultat : une barre pleine en haut de chaque
 * écran à en-tête, qui coupe l'iceberg net et n'apparaît dans aucun de nos fichiers de style.
 *
 * On ne passe pas par `headerTransparent` : il ferait passer le contenu SOUS l'en-tête et
 * décalerait tous les écrans concernés. Un fond transparent suffit, la mise en page ne bouge pas.
 */
export function apparenceDEnTete(): {
  headerStyle: { backgroundColor: string };
  headerShadowVisible: boolean;
  headerBackground: () => React.ReactElement;
} {
  return {
    headerStyle: { backgroundColor: 'transparent' },
    // L'ombre portée redessinerait la ligne que la transparence vient d'effacer.
    headerShadowVisible: false,
    /*
     * ET UNE PLAQUE DERRIÈRE, parce que la toile est un RENDU et non un aplat.
     *
     * Transparent seul suffisait tant que le fond était une nuance sage. Le fond est maintenant
     * l'image de la planche : elle va du noir de la quille au blanc des caustiques, et le titre
     * d'un écran tombait pile sur ces caustiques — illisible. Le verre porte le titre, comme il
     * porte tout le reste.
     */
    headerBackground: () => <GlassSurface testID="glass-header" radius={0} strong style={StyleSheet.absoluteFill} />,
  };
}

/**
 * Le fond d'une feuille modale — même matière que les barres, coins arrondis en haut seulement.
 *
 * `@gorhom/bottom-sheet` attend un style d'arrière-plan, pas un composant : il n'y a donc pas de
 * flou ici, seulement le voile et le liseré. Une feuille couvre de toute façon l'essentiel de
 * l'écran, où il n'y a presque rien à flouter.
 */
export function fondDeFeuille(theme: ThemeTokens): ViewStyle {
  return {
    backgroundColor: theme.glassStrong,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: theme.glassBorder,
  };
}
