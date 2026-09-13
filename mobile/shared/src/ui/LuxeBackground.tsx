import React, { useEffect, useMemo } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import { BlurMask, Canvas, Circle, Group, LinearGradient, RadialGradient, Rect, vec } from '@shopify/react-native-skia';
import { Easing, useDerivedValue, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';
import { traduireMaintenant } from '@/i18n';
import { IcebergVolumetrique } from './IcebergVolumetrique';

/**
 * ICEBERG — le même objet, vu de deux profondeurs.
 *
 * EN CLAIR ON EST AU-DESSUS. Ciel pâle, la couronne posée haut dans l'écran, le reste sortant par
 * le bas : dix pour cent, et on le voit.
 *
 * EN SOMBRE ON EST DESSOUS. La surface luit tout en haut, la masse entière descend jusqu'à
 * s'éteindre, et quelques bulles remontent vers la lumière.
 *
 * L'OBJET NE CHANGE PAS D'UN THÈME À L'AUTRE — seul le cadrage change. C'est tout le concept, et
 * c'est pour cela que le maillage est unique : deux modèles distincts trahiraient l'idée à la
 * première comparaison.
 *
 * UNE SEULE HORLOGE. La rotation et les bulles lisent la même phase 0→1 : la boucle se referme
 * exactement, sans raccord visible. Le mouvement réduit ne ralentit rien, il ne lance jamais la
 * boucle — la phase reste à zéro, et l'image de repos est une composition valide.
 *
 * CE QU'IL FAUT POUR LE VOIR : Skia s'installe par des liaisons natives, absentes d'Expo Go. Il
 * faut un development build (`npx expo run:android`).
 */

/** Un tour complet du modèle. Les bulles en sont un multiple entier. */
const PERIODE = 48000;

const NOMBRE_DE_BULLES = 8;

export function LuxeBackground() {
  const { isDark } = useThemeColors();
  const mouvementReduit = useReducedMotion();
  const { width, height } = useWindowDimensions();

  const phase = useSharedValue(0);

  useEffect(() => {
    if (mouvementReduit) {
      phase.value = 0;

      return;
    }

    phase.value = withRepeat(withTiming(1, { duration: PERIODE, easing: Easing.linear }), -1, false);
  }, [mouvementReduit, phase]);

  const glace = colors.mode.iceberg.immerge;
  const ciel = colors.mode.iceberg.emerge;
  const bulles = useMemo(() => semerLesBulles(width), [width]);

  const etiquette = mouvementReduit
    ? traduireMaintenant('luxe_background.fond_decoratif_sans_animation')
    : traduireMaintenant('luxe_background.fond_decoratif');

  if (!isDark) {
    return (
      <View
        testID="luxe-background-clair"
        style={StyleSheet.absoluteFill}
        pointerEvents="none"
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
        accessibilityLabel={etiquette}
      >
        <Canvas style={StyleSheet.absoluteFill}>
          {/*
            LE CIEL DESCEND VERS LE BLANC, et non l'inverse. Le dégradé partait du blanc en haut
            pour finir bleu en bas : la banquise se retrouvait dans le ciel. Le froid est en haut,
            le champ de glace en bas — et c'est ce blanc franc qui détache la quille.
          */}
          <Rect x={0} y={0} width={width} height={height}>
            <LinearGradient
              start={vec(0, 0)}
              end={vec(width * 0.22, height)}
              colors={[ciel.maillageSombre, ciel.page, ciel.maillageClair]}
              positions={[0, 0.42, 1]}
            />
          </Rect>

          {/* La couronne, cadree haut : on ne montre que la part emergee. */}
          <IcebergVolumetrique phase={phase} largeur={width} hauteur={height} sombre={false} />
        </Canvas>
      </View>
    );
  }

  return (
    <View
      testID="luxe-background"
      style={StyleSheet.absoluteFill}
      pointerEvents="none"
      // Un fond n'a rien à dire : le laisser accessible ferait annoncer « image » avant chaque écran.
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      accessibilityLabel={etiquette}
    >
      <Canvas style={StyleSheet.absoluteFill}>
        {/* La colonne d'eau s'éteint VITE : sous la surface il ne reste presque rien. */}
        <Rect x={0} y={0} width={width} height={height}>
          <LinearGradient
            start={vec(0, 0)}
            end={vec(0, height)}
            colors={[glace.eau, glace.abysse]}
            positions={[0.08, 0.62]}
          />
        </Rect>

        {/*
          LA SURFACE VUE DE DESSOUS — la seule source de lumière de cet écran, et il faut qu'elle
          se voie. Elle porte exactement la couleur du PLAFOND de la scène : plus claire, elle
          emporterait le texte posé dessus ; plus sombre, on ne saurait plus d'où vient le jour.
        */}
        <Group opacity={0.62}>
          <Rect x={0} y={0} width={width} height={height}>
            <RadialGradient
              c={vec(width * 0.5, -height * 0.03)}
              r={height * 0.3}
              colors={[glace.plafond, `${glace.plafond}47`, `${glace.plafond}00`]}
              positions={[0, 0.5, 1]}
            />
          </Rect>
        </Group>

        {/* L'objet entier, quille comprise — c'est la vue qu'on n'a jamais de la surface. */}
        <IcebergVolumetrique phase={phase} largeur={width} hauteur={height} sombre />

        {bulles.map((bulle, index) => (
          <BulleQuiMonte key={bulle.cle} bulle={bulle} phase={phase} index={index} hauteur={height} />
        ))}
      </Canvas>
    </View>
  );
}

interface Bulle { cle: string; x: number; r: number; depart: number }

/** Une bulle qui remonte vers la surface, s'efface aux deux bouts, et recommence. */
function BulleQuiMonte({
  bulle,
  phase,
  index,
  hauteur,
}: {
  bulle: Bulle;
  phase: { value: number };
  index: number;
  hauteur: number;
}) {
  const harmonique = 2 + (index % 2);

  const transformation = useDerivedValue(() => {
    const montee = (phase.value * harmonique + bulle.depart) % 1;

    return [{ translateY: hauteur - montee * hauteur * 1.1 }];
  });

  /* L'opacité s'éteint aux deux extrémités : sans cela, le bouclage se verrait en haut d'écran. */
  const opacite = useDerivedValue(() => {
    const montee = (phase.value * harmonique + bulle.depart) % 1;

    return Math.sin(montee * Math.PI) * 0.7;
  });

  return (
    <Group transform={transformation} opacity={opacite}>
      {/* UN ANNEAU, PAS UN DISQUE : c'est la paroi qui capte la lumière, l'intérieur est de l'eau. */}
      <Circle cx={bulle.x} cy={0} r={bulle.r} style="stroke" strokeWidth={1.4} color="rgba(190, 226, 245, 0.42)" />
      <Circle cx={bulle.x} cy={0} r={bulle.r} color="rgba(127, 196, 232, 0.09)" />
      {/* Le reflet reste SOUS le plafond de la scène : à 0,75 il était le seul pixel à le dépasser. */}
      <Circle
        cx={bulle.x - bulle.r * 0.32}
        cy={-bulle.r * 0.34}
        r={Math.max(0.9, bulle.r * 0.2)}
        color="rgba(255, 255, 255, 0.45)"
      >
        <BlurMask blur={0.6} style="normal" />
      </Circle>
    </Group>
  );
}

/**
 * Le semis est DÉTERMINISTE : à largeur égale, les mêmes bulles. Un élément qui change de place
 * entre deux rendus se remarque immédiatement.
 */
function semerLesBulles(largeur: number): Bulle[] {
  let etat = 1337;
  const suivant = () => {
    etat = (etat * 1664525 + 1013904223) % 4294967296;

    return etat / 4294967296;
  };

  return Array.from({ length: NOMBRE_DE_BULLES }, (_, i) => ({
    cle: `bulle-${i}`,
    // Écartées du centre : le milieu appartient à l'iceberg.
    x: (suivant() < 0.5 ? 0.06 + suivant() * 0.2 : 0.74 + suivant() * 0.2) * largeur,
    // Biaisé vers le petit : une répartition uniforme donne des ballons, pas des bulles.
    r: 3 + Math.pow(suivant(), 2.2) * 8,
    depart: suivant(),
  }));
}
