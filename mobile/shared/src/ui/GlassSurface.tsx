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
 * EN MODE CLAIR, RIEN DE TOUT ÇA. Une `View` ordinaire avec le fond de carte du thème. Le verre
 * est un traitement du sombre ; en plein soleil un prestataire a besoin de contraste.
 */
export function GlassSurface({
  children,
  strong = false,
  style,
  radius = 20,
  testID = 'glass-surface',
}: GlassSurfaceProps) {
  const theme = useThemeColors();

  if (!theme.isDark) {
    return (
      <View testID={testID} style={[{ backgroundColor: theme.card, borderRadius: radius }, style]}>
        {children}
      </View>
    );
  }

  const voile = strong ? theme.glassStrong : theme.glass;

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
          tint="dark"
          style={[StyleSheet.absoluteFill, { borderRadius: radius }]}
        />
        <View
          testID="glass-veil"
          style={[StyleSheet.absoluteFill, { backgroundColor: voile, borderRadius: radius }]}
        />
        <View testID="glass-edge-top" style={[styles.areteHaute, { backgroundColor: LUMIERE_HAUTE }]} />
        <View testID="glass-edge-bottom" style={[styles.areteBasse, { backgroundColor: LUMIERE_BASSE }]} />
      </View>

      {children}
    </View>
  );
}

/*
 * Les deux arêtes ne passent pas par le thème : elles n'existent qu'en mode sombre, où le
 * traitement est fixe. Un jeton de plus dans `useThemeColors` pour deux valeurs employées une
 * seule fois ferait grossir la fabrique sans rien rendre configurable.
 */
const LUMIERE_HAUTE = 'rgba(255, 255, 255, 0.22)';
const LUMIERE_BASSE = 'rgba(255, 255, 255, 0.05)';

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
