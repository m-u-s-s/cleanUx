import React from 'react';
import { StyleSheet, View } from 'react-native';
import { DarkTheme, DefaultTheme, type Theme } from '@react-navigation/native';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { LuxeBackground } from './LuxeBackground';

/**
 * La coque nuit : une seule toile pour toute l'application.
 *
 * POURQUOI ICI ET NON DANS `Screen`. Monter le fond dans le composant d'écran donnerait une toile
 * Skia PAR ÉCRAN — 28 gouttes redessinées à chaque navigation, et des gouttes qui changent de place
 * en passant d'un onglet à l'autre puisque chaque toile aurait sa propre vie. Une seule toile à la
 * racine, sous la navigation, donne un fond continu : c'est le contenu qui glisse dessus, comme
 * derrière une vitre.
 *
 * Elle enveloppe le conteneur de navigation, pas l'inverse. Les barres et les écrans sont
 * transparents DANS LES DEUX THÈMES et laissent voir cette toile : depuis l'iceberg, le clair a lui
 * aussi quelque chose à montrer.
 */
export function NightShell({ children }: { children: React.ReactNode }) {
  return (
    <View style={styles.coque}>
      <LuxeBackground />
      {children}
    </View>
  );
}

/**
 * Le thème de React Navigation, accordé à la toile.
 *
 * `background: 'transparent'` EST LE POINT ESSENTIEL, ET DANS LES DEUX THÈMES. Le conteneur de
 * navigation peint son propre fond sous chaque écran ; laissé opaque, il masque entièrement la
 * toile. C'est le genre de couche qu'on oublie parce qu'elle n'apparaît dans aucun de nos fichiers :
 * le clair est resté un aplat uniforme tant qu'on a rendu ici le thème par défaut.
 */
export function themeDeNavigation(isDark: boolean): Theme {
  const base = isDark ? DarkTheme : DefaultTheme;
  const glace = colors.mode.iceberg;

  return {
    ...base,
    colors: {
      ...base.colors,
      primary: isDark ? colors.brand[300] : colors.brand[600],
      background: 'transparent',
      card: 'transparent',
      text: isDark ? glace.immerge.texte : glace.emerge.texte,
      border: isDark ? 'rgba(234, 243, 249, 0.14)' : 'rgba(11, 26, 36, 0.10)',
    },
  };
}

/** Raccourci pour les racines d'application : le thème accordé au schéma courant. */
export function useThemeDeNavigation(): Theme {
  const { isDark } = useThemeColors();

  return themeDeNavigation(isDark);
}

const styles = StyleSheet.create({
  coque: { flex: 1 },
});
