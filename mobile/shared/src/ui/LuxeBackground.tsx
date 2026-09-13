import React from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import { Canvas, Image, Rect, useAnimatedImageValue, useImage } from '@shopify/react-native-skia';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';
import { traduireMaintenant } from '@/i18n';

/**
 * ICEBERG — le rendu de la planche « Volumétrique », qui tourne.
 *
 * POURQUOI UNE VIDÉO ET NON UNE ANIMATION. L'objet est un rendu photoréaliste : le faire tourner
 * demande de nouvelles faces, pas une transformation de l'image existante. Une image qu'on incline
 * ou qu'on étire ne tourne pas, elle se déforme, et le maillage a été essayé trois fois sans
 * jamais atteindre la planche. Ce qui tourne ici est donc le rendu lui-même : sept secondes qui
 * bouclent, l'iceberg pivote sur son axe et la surface de l'eau ondule avec lui.
 *
 * POURQUOI UNE IMAGE ANIMÉE ET NON UNE VIDÉO. Deux lecteurs ont été essayés puis écartés, et
 * chacun a laissé une trace mesurée :
 *
 *   `expo-video` embarque media3, dont le chargement natif s'ajoute au démarrage du processus —
 *   trois lancements à froid, trois ANR « failed to complete startup », l'application tuée avant
 *   d'avoir rien affiché. Différer le montage du lecteur n'y change rien : ces bibliothèques se
 *   chargent avant que le JS ne tourne.
 *
 *   `useVideo` de Skia s'appuie sur le décodeur de la plateforme et refuse en dessous d'Android 8 :
 *   « Skia Videos are only supported on API 26 and above ». Le projet est en minSdk 24, et relever
 *   le plancher de l'application pour un fond décoratif n'est pas un échange raisonnable.
 *
 * Une image ANIMÉE, elle, est décodée par Skia lui-même, image par image, sans codec de plateforme
 * et sans dépendance nouvelle. Un seul moteur de rendu, rien de neuf au démarrage, aucun plancher
 * d'API déplacé. C'est douze images par seconde plutôt que vingt-quatre — pour une rotation aussi
 * lente, la différence ne se voit pas.
 *
 * LA BOUCLE EST SANS COUTURE. La dernière seconde est fondue sur la première : sans ce
 * recouvrement, le saut de fin de boucle se voit une fois toutes les sept secondes, et c'est le
 * genre de défaut qu'on ne remarque qu'après l'avoir vu une fois — puis qu'on ne peut plus ignorer.
 *
 * L'IMAGE FIXE RESTE, ET N'EST PAS UN DOUBLON. Elle est la PREMIÈRE IMAGE de la vidéo, extraite au
 * montage : elle couvre le temps du décodage sans que la composition ne saute, et elle est ce
 * qu'on voit quand le mouvement est réduit — là, aucune vidéo n'est ouverte du tout.
 *
 * CE QU'IL FAUT POUR LE VOIR : Skia s'installe par des liaisons natives, absentes d'Expo Go. Il
 * faut un development build (`npx expo run:android`).
 */

const ICEBERG_CLAIR = require('../../assets/iceberg-clair.webp');
const ICEBERG_SOMBRE = require('../../assets/iceberg-sombre.webp');

const ROTATION_CLAIRE = require('../../assets/rotation-clair.webp');
const ROTATION_SOMBRE = require('../../assets/rotation-sombre.webp');

export function LuxeBackground() {
  const { isDark } = useThemeColors();
  const mouvementReduit = useReducedMotion();
  const { width, height } = useWindowDimensions();

  const image = useImage(isDark ? ICEBERG_SOMBRE : ICEBERG_CLAIR);

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
          <Image image={image} x={0} y={0} width={width} height={height} fit="cover" />
        ) : null}

        {mouvementReduit ? null : (
          /*
            LA CLÉ FORCE UN DÉCODEUR NEUF À CHAQUE THÈME.

            `useAnimatedImageValue` garde le sien pour la durée de vie du composant : changer la
            source ne le relance pas. Sans cette clé, basculer en sombre laissait l'animation
            CLAIRE tourner derrière des cartes sombres — mesuré à l'écran, et invisible aux tests
            puisque le composant rendait bien quelque chose.
          */
          <RotationDeLIceberg
            key={isDark ? 'nuit' : 'jour'}
            sombre={isDark}
            largeur={width}
            hauteur={height}
          />
        )}
      </Canvas>
    </View>
  );
}

/** La couche animée. Isolée pour que sa clé puisse remonter un décodeur neuf. */
function RotationDeLIceberg({
  sombre,
  largeur,
  hauteur,
}: {
  sombre: boolean;
  largeur: number;
  hauteur: number;
}) {
  const rotation = useAnimatedImageValue(sombre ? ROTATION_SOMBRE : ROTATION_CLAIRE);

  return <Image image={rotation} x={0} y={0} width={largeur} height={hauteur} fit="cover" />;
}
