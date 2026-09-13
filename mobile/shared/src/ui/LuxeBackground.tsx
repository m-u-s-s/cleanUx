import React, { useEffect } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import { Canvas, Image, Rect, useImage } from '@shopify/react-native-skia';
import { Easing, useDerivedValue, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';
import { traduireMaintenant } from '@/i18n';

/**
 * ICEBERG — le rendu de la planche « Volumétrique », posé tel quel.
 *
 * POURQUOI UNE IMAGE ET NON UN MAILLAGE. Trois versions successives ont tenté de reconstruire cet
 * iceberg en géométrie : d'abord un maillage Skia à facettes, puis une crête à flèches. Aucune n'a
 * approché la planche, et aucune ne pouvait : ce qu'elle montre est un rendu photoréaliste — glace
 * cristalline translucide, diffusion sous la surface, quille rocheuse presque noire, caustiques à
 * la surface de l'eau. Passer à three.js n'y aurait rien changé : il aurait fallu un modèle 3D
 * sculpté, des matériaux et une HDRI, c'est-à-dire produire ce rendu-là de toute façon. Le vrai
 * « copier/coller » d'un rendu, c'est le rendu.
 *
 * DEUX IMAGES, UN SEUL OBJET. Le même iceberg, éclairé deux fois : au-dessus de la ligne de
 * flottaison en clair, vu de dessous à travers les caustiques en sombre. 142 et 128 Ko.
 *
 * CE QUI BOUGE. La planche fait tourner le modèle sur son axe ; une image ne tourne pas. Elle
 * DÉRIVE — une lente respiration verticale, aller et retour, sur une minute. Le mouvement réduit
 * la fige, et l'image de repos est une composition valide.
 *
 * CE QU'IL FAUT POUR LE VOIR : Skia s'installe par des liaisons natives, absentes d'Expo Go. Il
 * faut un development build (`npx expo run:android`).
 */

/** Une respiration complète, aller et retour. */
const PERIODE = 30000;

/** L'amplitude de la dérive, en part de la hauteur d'écran. Au-delà, on voit l'image glisser. */
const DERIVE = 0.014;

const ICEBERG_CLAIR = require('../../assets/iceberg-clair.webp');
const ICEBERG_SOMBRE = require('../../assets/iceberg-sombre.webp');

export function LuxeBackground() {
  const { isDark } = useThemeColors();
  const mouvementReduit = useReducedMotion();
  const { width, height } = useWindowDimensions();

  const image = useImage(isDark ? ICEBERG_SOMBRE : ICEBERG_CLAIR);
  const respiration = useSharedValue(0);

  useEffect(() => {
    if (mouvementReduit) {
      respiration.value = 0;

      return;
    }

    respiration.value = withRepeat(
      withTiming(1, { duration: PERIODE, easing: Easing.inOut(Easing.sin) }),
      -1,
      true,
    );
  }, [mouvementReduit, respiration]);

  /*
   * CADRAGE « COUVRIR », CALCULÉ ICI ET NON DÉLÉGUÉ À `fit`.
   *
   * `fit="cover"` recadre au plus juste : il ne reste alors aucune marge, et la dérive ferait
   * apparaître une bande vide en haut ou en bas. On dessine donc un peu plus grand que l'écran, et
   * la dérive se promène dans cette marge.
   */
  const marge = height * DERIVE * 2;
  const hauteurDessinee = height + marge * 2;

  const y = useDerivedValue(() => -marge - (respiration.value - 0.5) * marge);

  const fond = isDark ? colors.mode.iceberg.immerge.abysse : colors.mode.iceberg.emerge.page;

  const etiquette = mouvementReduit
    ? traduireMaintenant('luxe_background.fond_decoratif_sans_animation')
    : traduireMaintenant('luxe_background.fond_decoratif');

  return (
    <View
      testID={isDark ? 'luxe-background' : 'luxe-background-clair'}
      style={StyleSheet.absoluteFill}
      pointerEvents="none"
      // Un fond n'a rien à dire : le laisser accessible ferait annoncer « image » avant chaque écran.
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      accessibilityLabel={etiquette}
    >
      <Canvas style={StyleSheet.absoluteFill}>
        {/* L'aplat de secours. Sans lui, l'écran est transparent le temps du décodage. */}
        <Rect x={0} y={0} width={width} height={height} color={fond} />

        {image ? (
          <Image
            image={image}
            x={0}
            y={y}
            width={width}
            height={hauteurDessinee}
            fit="cover"
          />
        ) : null}
      </Canvas>
    </View>
  );
}
