import React, { useMemo } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import {
  Canvas,
  Circle,
  Group,
  LinearGradient,
  RadialGradient,
  Rect,
  vec,
} from '@shopify/react-native-skia';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';

/** Réglages validés sur l'aperçu : 28 gouttes, lueur à 0.30. */
const NOMBRE_DE_GOUTTES = 28;
const LUEUR = 0.3;

/**
 * Le fond nuit des écrans en mode sombre.
 *
 * TROIS COUCHES, une seule toile. Un dégradé nuit, une lueur de marque diffuse en haut, et des
 * gouttes d'eau. Skia ne dessine QUE ce fond : les cartes et les boutons sont rendus par
 * `expo-blur`, parce qu'ils floutent ce qu'il y a derrière eux — ce que Skia ne fait pas, il
 * redessinerait.
 *
 * SEULES LES GROSSES GOUTTES GLISSENT. Sur une vitre, les petites tiennent par tension
 * superficielle ; les faire toutes descendre donne « des cercles qui tombent », pas de l'eau.
 * C'est le détail qui sépare l'effet de sa caricature.
 *
 * EN MODE CLAIR, IL REND UN FOND SOBRE — et c'est un ajout mesuré, pas un revirement.
 *
 * La règle d'origine disait « rien en clair : un prestataire en plein soleil a besoin de
 * contraste, pas de translucidité ». Elle reste vraie, et ce fond ne la contredit pas : trois
 * auras très diffuses, AUCUNE goutte, aucun mouvement. Leur opacité maximale est de 0,10 —
 * un texte posé dessus perd moins d'un dixième de point de contraste.
 *
 * Sans elles, le verre clair n'a rien à filtrer : une surface translucide posée sur un aplat
 * uni est indiscernable d'une surface opaque, et tout le traitement disparaît.
 *
 * CE QU'IL FAUT SAVOIR AVANT DE LE REGARDER : Skia s'installe par des liaisons natives. Il ne
 * tourne donc pas dans Expo Go — il faut un development build (`npx expo run:android` ou
 * `run:ios`) pour voir ce fond sur un appareil.
 */
export function LuxeBackground() {
  const { isDark } = useThemeColors();
  const mouvementReduit = useReducedMotion();
  const { width, height } = useWindowDimensions();

  /*
   * Les gouttes sont semées une fois, de façon DÉTERMINISTE.
   *
   * Un tirage aléatoire à chaque rendu les ferait sauter d'une position à l'autre à chaque
   * changement d'état de l'écran — un scintillement permanent, et impossible à reproduire pour
   * qui voudrait le corriger.
   */
  const gouttes = useMemo(() => semer(width, height), [width, height]);

  if (!isDark) {
    return (
      <View
        testID="luxe-background-clair"
        style={StyleSheet.absoluteFill}
        pointerEvents="none"
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <Canvas style={StyleSheet.absoluteFill}>
          {/* Le voile bleuté : le fond n'est jamais blanc pur, sans quoi le verre posé
              dessus ne se voit pas. Les mêmes valeurs que `--brio-bg` du web. */}
          <Rect x={0} y={0} width={width} height={height}>
            <LinearGradient
              start={vec(0, 0)}
              end={vec(width * 0.4, height)}
              colors={['#f3f5fb', '#eef1f8', '#e6ebf5']}
              positions={[0, 0.5, 1]}
            />
          </Rect>

          {/* Trois auras, aux mêmes places que `body::before` du web : deux en haut, une
              en bas. Leur opacité plafonne à 0,10 — assez pour donner de la matière au
              verre, trop peu pour peser sur la lisibilité. */}
          <Rect x={0} y={0} width={width} height={height}>
            <RadialGradient
              c={vec(width * 0.12, height * 0.08)}
              r={height * 0.55}
              colors={['rgba(120, 160, 255, 0.10)', 'rgba(120, 160, 255, 0.03)', 'rgba(120, 160, 255, 0)']}
              positions={[0, 0.5, 1]}
            />
          </Rect>

          <Rect x={0} y={0} width={width} height={height}>
            <RadialGradient
              c={vec(width * 0.9, height * 0.14)}
              r={height * 0.5}
              colors={['rgba(255, 182, 72, 0.09)', 'rgba(255, 182, 72, 0.03)', 'rgba(255, 182, 72, 0)']}
              positions={[0, 0.5, 1]}
            />
          </Rect>

          <Rect x={0} y={0} width={width} height={height}>
            <RadialGradient
              c={vec(width * 0.6, height * 0.94)}
              r={height * 0.6}
              colors={['rgba(139, 123, 255, 0.08)', 'rgba(139, 123, 255, 0.02)', 'rgba(139, 123, 255, 0)']}
              positions={[0, 0.5, 1]}
            />
          </Rect>
        </Canvas>
      </View>
    );
  }

  return (
    <View
      testID="luxe-background"
      style={StyleSheet.absoluteFill}
      pointerEvents="none"
      // Un fond n'a rien à dire : le laisser accessible ferait annoncer « image » avant chaque
      // écran, sans qu'aucune information ne suive.
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      accessibilityLabel={mouvementReduit ? 'Fond décoratif, sans animation' : 'Fond décoratif'}
    >
      <Canvas style={StyleSheet.absoluteFill}>
        {/* Le dégradé nuit, en diagonale douce. */}
        <Rect x={0} y={0} width={width} height={height}>
          <LinearGradient
            start={vec(0, 0)}
            end={vec(width * 0.35, height)}
            colors={[
              colors.mode.showcase.nightSoft,
              '#080d18',
              colors.mode.showcase.night,
            ]}
            positions={[0, 0.55, 1]}
          />
        </Rect>

        {/*
          La lueur de marque : haute et diffuse. Elle situe la marque sans éclairer l'écran —
          une lueur trop basse ou trop dense passerait sous le contenu et le rendrait gris.
        */}
        <Rect x={0} y={0} width={width} height={height}>
          <RadialGradient
            c={vec(width * 0.72, height * 0.06)}
            r={height * 0.62}
            colors={[
              `rgba(99, 102, 241, ${LUEUR * 0.55})`,
              `rgba(99, 102, 241, ${LUEUR * 0.14})`,
              'rgba(99, 102, 241, 0)',
            ]}
            positions={[0, 0.45, 1]}
          />
        </Rect>

        {gouttes.map((goutte) => (
          <Group key={goutte.cle}>
            {/* Le corps : plus clair en haut à gauche, comme une lentille éclairée d'en haut. */}
            <Circle cx={goutte.x} cy={goutte.y} r={goutte.r}>
              <RadialGradient
                c={vec(goutte.x - goutte.r * 0.3, goutte.y - goutte.r * 0.35)}
                r={goutte.r * 1.4}
                colors={[
                  'rgba(232, 238, 252, 0.20)',
                  'rgba(160, 180, 220, 0.07)',
                  'rgba(10, 16, 30, 0.20)',
                ]}
                positions={[0, 0.55, 1]}
              />
            </Circle>

            {/* L'éclat spéculaire — le point qui fait lire « eau » et non « cercle ». */}
            {goutte.r > 3.2 ? (
              <Circle
                cx={goutte.x - goutte.r * 0.34}
                cy={goutte.y - goutte.r * 0.38}
                r={goutte.r * 0.17}
                color="rgba(255, 255, 255, 0.42)"
              />
            ) : null}
          </Group>
        ))}
      </Canvas>
    </View>
  );
}

interface Goutte {
  cle: string;
  x: number;
  y: number;
  r: number;
}

/**
 * Sème les gouttes de façon déterministe.
 *
 * Le générateur est un mélangeur entier trivial plutôt que `Math.random` : à dimensions égales,
 * la même vitre. Une goutte qui change de place entre deux rendus se remarque immédiatement.
 *
 * La distribution des rayons est biaisée vers le petit (puissance 2,2) : sur une vitre, les
 * grosses gouttes sont rares. Une répartition uniforme donnerait une bulle de savon.
 */
function semer(largeur: number, hauteur: number): Goutte[] {
  const gouttes: Goutte[] = [];
  let graine = 1337;

  const suivant = () => {
    graine = (graine * 1664525 + 1013904223) % 4294967296;

    return graine / 4294967296;
  };

  for (let i = 0; i < NOMBRE_DE_GOUTTES; i++) {
    gouttes.push({
      cle: `goutte-${i}`,
      x: suivant() * largeur,
      y: suivant() * hauteur,
      r: 2 + Math.pow(suivant(), 2.2) * 13,
    });
  }

  return gouttes;
}
