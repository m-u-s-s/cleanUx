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
 * Elle enveloppe le conteneur de navigation, pas l'inverse. Les barres et les écrans deviennent
 * transparents en sombre et laissent voir cette toile ; en clair elle ne rend rien et les fonds
 * pleins d'origine reprennent la main.
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
 * Le thème de React Navigation, accordé au fond nuit.
 *
 * `background: 'transparent'` EST LE POINT ESSENTIEL. Le conteneur de navigation peint son propre
 * fond sous chaque écran ; laissé opaque, il masquerait entièrement la toile et le travail des
 * gouttes ne se verrait nulle part. C'est le genre de couche qu'on oublie parce qu'elle n'apparaît
 * dans aucun de nos fichiers.
 *
 * En clair, le thème par défaut est rendu tel quel.
 */
export function themeDeNavigation(isDark: boolean): Theme {
  if (!isDark) {
    return DefaultTheme;
  }

  return {
    ...DarkTheme,
    colors: {
      ...DarkTheme.colors,
      primary: colors.brand[500],
      background: 'transparent',
      card: 'transparent',
      text: colors.mode.showcase.text,
      border: 'rgba(232, 238, 252, 0.14)',
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
