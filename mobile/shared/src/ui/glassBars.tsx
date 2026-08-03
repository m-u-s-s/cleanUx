import React from 'react';
import { StyleSheet, type ViewStyle } from 'react-native';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { GlassSurface } from './GlassSurface';

interface ApparenceDeBarre {
  tabBarStyle: ViewStyle;
  /** Absent en mode clair : la barre garde son fond plein. */
  tabBarBackground?: () => React.ReactElement;
}

/**
 * L'apparence d'une barre d'onglets pour le thème courant.
 *
 * POURQUOI UNE FONCTION ET NON UN COMPOSANT. Les deux navigateurs — celui du prestataire et celui
 * de la console d'administration — construisent leurs `screenOptions` séparément. Une fonction
 * partagée garantit qu'ils ne divergeront pas ; deux copies du même objet de style, si.
 *
 * EN SOMBRE, LA BARRE S'EFFACE. Fond transparent, liseré à zéro, et une plaque de verre en
 * arrière-plan. Une barre opaque couperait le fond nuit d'un trait plat en bas de chaque écran,
 * et les gouttes s'arrêteraient net sur une ligne.
 *
 * `borderTopWidth: 0` EST AUSSI IMPORTANT que la transparence. React Navigation pose un liseré
 * haut par défaut ; le rendre transparent sans annuler sa largeur laisse une bande d'un pixel qui
 * masque le fond au lieu de le laisser passer.
 *
 * EN CLAIR, RIEN NE CHANGE : fond plein et liseré du thème, exactement comme avant.
 */
export function apparenceDeBarre(theme: ThemeTokens): ApparenceDeBarre {
  if (!theme.isDark) {
    return {
      tabBarStyle: {
        backgroundColor: theme.bg,
        borderTopColor: theme.border,
      },
    };
  }

  return {
    tabBarStyle: {
      backgroundColor: 'transparent',
      borderTopWidth: 0,
      // Sans cela, l'ombre portée d'iOS dessine sous la barre un halo qui trahit sa présence.
      elevation: 0,
      shadowOpacity: 0,
    },
    tabBarBackground: () => (
      /*
       * `radius={0}` : une barre d'onglets touche les trois bords de l'écran. Le rayon par défaut
       * de la plaque arrondirait ses coins bas et laisserait deux encoches sur le fond nuit.
       */
      <GlassSurface testID="glass-bar" radius={0} strong style={StyleSheet.absoluteFill} />
    ),
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
  return theme.isDark
    ? { backgroundColor: theme.glassStrong, borderWidth: StyleSheet.hairlineWidth, borderColor: theme.glassBorder }
    : { backgroundColor: theme.bg };
}
