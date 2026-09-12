import React from 'react';
import { StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';
import { BlurView } from 'expo-blur';
import { useThemeColors } from '@/theme/useThemeColors';

/** Réglage validé sur l'aperçu : 18 px de flou. */
const FLOU = 18;

interface GlassSurfaceProps {
  children?: React.ReactNode;
  /** Voile plus dense — pour les surfaces qui portent du texte long. */
  strong?: boolean;
  /** Mise en page : marges, rayon, remplissage. Pas de couleur ici. */
  style?: StyleProp<ViewStyle>;
  /** Rayon des coins, propagé au flou qui doit être découpé au même rayon. */
  radius?: number;
  testID?: string;
}

/**
 * Une plaque de verre : cartes, panneaux, barres.
 *
 * QUATRE COUCHES, dans cet ordre. Le flou (ce qu'il y a derrière), le voile (la teinte du verre),
 * les deux arêtes (la lumière), puis le contenu. Chacune peut disparaître sans emporter les
 * autres — c'est tout l'intérêt de les séparer.
 *
 * LE VOILE NE VIT PAS DANS LE FLOU. Sur un appareil où `expo-blur` ne rend rien — c'est un repli
 * silencieux, pas une erreur — un `BlurView` devient une vue transparente. Si le voile était une
 * propriété du flou, le panneau disparaîtrait entièrement : du texte clair flottant sur le fond
 * nuit, sans cadre. Séparé, le voile survit seul et la carte reste lisible.
 *
 * LA LUMIÈRE VIENT D'EN HAUT. L'arête haute est nettement plus claire que la basse. Une bordure
 * uniforme lit « rectangle avec bordure » ; c'est l'asymétrie qui fait lire « plaque ».
 *
 * LE MODE CLAIR A LE VERRE, LUI AUSSI — depuis « Verre givré ». Il rendait auparavant une `View`
 * opaque, au motif qu'« en plein soleil un prestataire a besoin de contraste, pas de
 * translucidité ». La règle était juste, la conclusion ne l'était pas : un voile blanc à 0,72
 * posé sur le point le plus sombre du maillage compose #f5f7fb, soit 14,8:1 sous le texte. Le
 * verre clair ne coûte pas de contraste tant que le voile tient son plancher.
 *
 * Ce qui change avec le thème, ce n'est donc plus la PRÉSENCE du verre, c'est sa teinte et la
 * couleur de ses arêtes.
 */
export function GlassSurface({
  children,
  strong = false,
  style,
  radius = 20,
  testID = 'glass-surface',
}: GlassSurfaceProps) {
  const theme = useThemeColors();

  const voile = strong ? theme.glassStrong : theme.glass;
  const arete = theme.isDark ? ARETES.nuit : ARETES.jour;

  return (
    <View
      testID={testID}
      style={[styles.plaque, { borderRadius: radius, borderColor: theme.glassBorder }, style]}
    >
      {/*
        Les trois couches de matière sont décoratives : les masquer aux lecteurs d'écran évite
        d'annoncer trois vues vides avant chaque carte.
      */}
      <View
        style={StyleSheet.absoluteFill}
        pointerEvents="none"
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <BlurView
          testID="glass-blur"
          intensity={FLOU}
          tint={theme.isDark ? 'dark' : 'light'}
          style={[StyleSheet.absoluteFill, { borderRadius: radius }]}
        />
        <View
          testID="glass-veil"
          style={[StyleSheet.absoluteFill, { backgroundColor: voile, borderRadius: radius }]}
        />
        <View testID="glass-edge-top" style={[styles.areteHaute, { backgroundColor: arete.haute }]} />
        <View testID="glass-edge-bottom" style={[styles.areteBasse, { backgroundColor: arete.basse }]} />
      </View>

      {children}
    </View>
  );
}

/*
 * LA LUMIÈRE VIENT D'EN HAUT DANS LES DEUX THÈMES, mais pas de la même façon : sur la nuit
 * l'arête haute est un reflet blanc, sur le jour c'est un blanc franc et la base devient une
 * ombre d'ardoise. Une arête blanche en bas sur fond clair ne se verrait pas.
 *
 * Elles restent hors de `useThemeColors` : quatre valeurs employées à un seul endroit.
 */
const ARETES = {
  nuit: { haute: 'rgba(255, 255, 255, 0.22)', basse: 'rgba(255, 255, 255, 0.05)' },
  jour: { haute: 'rgba(255, 255, 255, 0.85)', basse: 'rgba(91, 127, 166, 0.14)' },
} as const;

const styles = StyleSheet.create({
  plaque: {
    borderWidth: StyleSheet.hairlineWidth,
    // Sans cela, le flou et le voile débordent des coins arrondis sur Android.
    overflow: 'hidden',
  },
  areteHaute: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: StyleSheet.hairlineWidth,
  },
  areteBasse: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    height: StyleSheet.hairlineWidth,
  },
});
