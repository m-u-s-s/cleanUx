import React, { useEffect, useMemo } from 'react';
import { StyleSheet, View, useWindowDimensions } from 'react-native';
import {
  BlurMask,
  Canvas,
  Circle,
  Group,
  LinearGradient,
  RadialGradient,
  Rect,
  vec,
} from '@shopify/react-native-skia';
import { Easing, useDerivedValue, useSharedValue, withRepeat, withTiming } from 'react-native-reanimated';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';
import { traduireMaintenant } from '@/i18n';

/**
 * MARÉE — le même monde, vu des deux côtés de la surface de l'eau.
 *
 * EN SOMBRE ON EST DESSOUS. La lumière tombe du haut en caustiques, s'éteint vers le bas, et
 * quelques bulles remontent. La hiérarchie devient physique : ce qui compte est près de la
 * lumière.
 *
 * EN CLAIR ON EST DESSUS. Un maillage pâle dérive lentement. Il n'est pas décoratif : sans
 * quelque chose à filtrer, une plaque de verre posée sur un aplat uni est indiscernable d'une
 * plaque opaque, et tout le traitement disparaît.
 *
 * UNE SEULE HORLOGE, DES HARMONIQUES ENTIÈRES. Chaque élément lit la même phase 0→1 multipliée
 * par un entier : la boucle se referme donc exactement, sans saut. Deux minuteurs indépendants
 * finiraient par battre l'un contre l'autre, et le raccord se verrait toutes les quelques
 * minutes — le genre de défaut qu'on ne reproduit jamais quand on le cherche.
 *
 * MOUVEMENT RÉDUIT : l'animation n'est pas ralentie, elle n'est jamais lancée. La phase reste à
 * zéro et tout le fichier rend son image de repos, qui est une composition valide.
 *
 * CE QU'IL FAUT SAVOIR AVANT DE LE REGARDER : Skia s'installe par des liaisons natives. Il ne
 * tourne donc pas dans Expo Go — il faut un development build (`npx expo run:android` ou
 * `run:ios`) pour voir ce fond sur un appareil.
 */

/** Un tour complet. Tout le reste en est un multiple entier. */
const PERIODE = 24000;

const NOMBRE_DE_BULLES = 9;
const NOMBRE_DE_CAUSTIQUES = 5;

/** Jusqu'où descend la lumière. Sous cette fraction de l'écran, il n'y a plus de caustiques. */
const BANDE_ECLAIREE = 0.42;

export function LuxeBackground() {
  const { isDark } = useThemeColors();
  const mouvementReduit = useReducedMotion();
  const { width, height } = useWindowDimensions();

  /*
   * La phase court de 0 à 1 sans fin. `Easing.linear` est indispensable : la moindre courbe
   * d'accélération rend le raccord visible, parce que la vitesse à l'arrivée diffère de celle
   * au départ.
   */
  const phase = useSharedValue(0);

  useEffect(() => {
    if (mouvementReduit) {
      phase.value = 0;

      return;
    }

    phase.value = withRepeat(
      withTiming(1, { duration: PERIODE, easing: Easing.linear }),
      -1,
      false,
    );
  }, [mouvementReduit, phase]);

  const eau = colors.mode.maree.profondeur;
  const givre = colors.mode.maree.givre;

  /*
   * Le ruban est une CHAÎNE DE CERCLES FLOUTÉS, pas un chemin.
   *
   * Un `Skia.Path` construit au rendu coûterait plus cher que tout le reste du fond réuni, et
   * n'existe pas hors appareil : le paquet ne fournit sa géométrie que derrière les liaisons
   * natives. Une dizaine de cercles noyés dans un flou de 10 px se lisent comme une nappe de
   * lumière, et se déplacent en bloc.
   *
   * Il déborde de l'écran des deux côtés pour que la translation ne découvre jamais son bout.
   */
  const perles = useMemo(() => semerLesPerles(width), [width]);

  const bulles = useMemo(() => semerLesBulles(width), [width]);
  const caustiques = useMemo(() => semerLesCaustiques(height), [height]);
  const auras = useMemo(() => semerLesAuras(width, height), [width, height]);

  if (!isDark) {
    return (
      <View
        testID="luxe-background-clair"
        style={StyleSheet.absoluteFill}
        pointerEvents="none"
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
        accessibilityLabel={
          mouvementReduit
            ? traduireMaintenant('luxe_background.fond_decoratif_sans_animation')
            : traduireMaintenant('luxe_background.fond_decoratif')
        }
      >
        <Canvas style={StyleSheet.absoluteFill}>
          {/* Le maillage : jamais blanc pur, sans quoi le verre posé dessus ne se voit pas. */}
          <Rect x={0} y={0} width={width} height={height}>
            <LinearGradient
              start={vec(0, 0)}
              end={vec(width * 0.4, height)}
              colors={[givre.maillageClair, givre.page, givre.maillageSombre]}
              positions={[0, 0.52, 1]}
            />
          </Rect>

          {auras.map((aura, index) => (
            <AuraQuiDerive key={aura.cle} aura={aura} phase={phase} index={index} />
          ))}
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
      accessibilityLabel={
        mouvementReduit
          ? traduireMaintenant('luxe_background.fond_decoratif_sans_animation')
          : traduireMaintenant('luxe_background.fond_decoratif')
      }
    >
      <Canvas style={StyleSheet.absoluteFill}>
        {/* La colonne d'eau : claire à la surface, éteinte au fond. */}
        <Rect x={0} y={0} width={width} height={height}>
          <LinearGradient
            start={vec(0, 0)}
            end={vec(0, height)}
            colors={[eau.eau, '#03202c', eau.abysse]}
            positions={[0, 0.46, 1]}
          />
        </Rect>

        {/* La lumière qui entre par la surface, très diffuse. */}
        <Rect x={0} y={0} width={width} height={height}>
          <RadialGradient
            c={vec(width * 0.5, -height * 0.05)}
            r={height * 0.72}
            colors={[
              'rgba(47, 217, 197, 0.20)',
              'rgba(47, 217, 197, 0.06)',
              'rgba(47, 217, 197, 0)',
            ]}
            positions={[0, 0.45, 1]}
          />
        </Rect>

        {caustiques.map((caustique, index) => (
          <CaustiqueQuiOndule
            key={caustique.cle}
            caustique={caustique}
            perles={perles}
            phase={phase}
            index={index}
          />
        ))}

        {bulles.map((bulle, index) => (
          <BulleQuiMonte key={bulle.cle} bulle={bulle} phase={phase} index={index} hauteur={height} />
        ))}
      </Canvas>
    </View>
  );
}

/* ── Les trois éléments animés ─────────────────────────────────────────────────────────────── */

interface Caustique { cle: string; y: number; opacite: number; amplitude: number; epaisseur: number }

/**
 * Un ruban de lumière qui glisse.
 *
 * Il ne fait qu'un aller-retour sinusoïdal : une translation continue exigerait de gérer le
 * bouclage, et un ruban qui revient sur ses pas est exactement ce que fait la lumière sur l'eau.
 */
function CaustiqueQuiOndule({
  caustique,
  perles,
  phase,
  index,
}: {
  caustique: Caustique;
  perles: Perle[];
  phase: { value: number };
  index: number;
}) {
  const harmonique = 1 + (index % 3);
  const decalage = index * 0.21;

  const transformation = useDerivedValue(() => [
    { translateX: Math.sin((phase.value * harmonique + decalage) * Math.PI * 2) * caustique.amplitude },
    { translateY: caustique.y },
  ]);

  return (
    <Group transform={transformation} opacity={caustique.opacite}>
      {/* Le flou est ce qui fait lire « nappe de lumière » et non « rangée de points ». */}
      <BlurMask blur={11} style="normal" />

      {perles.map((perle) => (
        <Circle
          key={perle.cle}
          cx={perle.x}
          cy={perle.y}
          r={caustique.epaisseur * perle.taille}
          color={colors.mode.maree.profondeur.caustique}
        />
      ))}
    </Group>
  );
}

interface Bulle { cle: string; x: number; r: number; depart: number }

/** Une bulle qui remonte, s'efface aux deux bouts, et recommence. */
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
  const harmonique = 1 + (index % 2);

  const transformation = useDerivedValue(() => {
    const montee = (phase.value * harmonique + bulle.depart) % 1;

    return [{ translateY: hauteur - montee * hauteur * 1.1 }];
  });

  /*
   * L'opacité s'éteint aux deux extrémités du trajet. Sans cela, la bulle disparaît d'un coup
   * en haut et réapparaît d'un coup en bas — le seul endroit où le bouclage se verrait.
   */
  const opacite = useDerivedValue(() => {
    const montee = (phase.value * harmonique + bulle.depart) % 1;

    return Math.sin(montee * Math.PI) * 0.5;
  });

  return (
    <Group transform={transformation} opacity={opacite}>
      <Circle cx={bulle.x} cy={0} r={bulle.r} color="rgba(200, 240, 238, 0.55)" />
      <Circle cx={bulle.x - bulle.r * 0.3} cy={-bulle.r * 0.3} r={bulle.r * 0.28} color="rgba(255, 255, 255, 0.7)" />
    </Group>
  );
}

interface Aura { cle: string; x: number; y: number; r: number; couleur: string; course: number }

/** Une aura pâle qui dérive sur une trajectoire fermée. */
function AuraQuiDerive({ aura, phase, index }: { aura: Aura; phase: { value: number }; index: number }) {
  const harmonique = 1 + (index % 2);
  const decalage = index * 0.27;

  const transformation = useDerivedValue(() => {
    const angle = (phase.value * harmonique + decalage) * Math.PI * 2;

    // Une figure de Lissajous : la trajectoire se referme sur elle-même, donc jamais de saut.
    return [
      { translateX: Math.cos(angle) * aura.course },
      { translateY: Math.sin(angle * 2) * aura.course * 0.55 },
    ];
  });

  return (
    <Group transform={transformation}>
      <Circle cx={aura.x} cy={aura.y} r={aura.r}>
        <RadialGradient
          c={vec(aura.x, aura.y)}
          r={aura.r}
          colors={[aura.couleur, 'rgba(255, 255, 255, 0)']}
          positions={[0, 1]}
        />
      </Circle>
    </Group>
  );
}

/* ── Les semis, déterministes ──────────────────────────────────────────────────────────────── */

/**
 * Le générateur est un mélangeur entier trivial plutôt que `Math.random` : à dimensions égales,
 * la même eau. Un élément qui change de place entre deux rendus se remarque immédiatement.
 */
function tirage(graine: number) {
  let etat = graine;

  return () => {
    etat = (etat * 1664525 + 1013904223) % 4294967296;

    return etat / 4294967296;
  };
}

function semerLesCaustiques(hauteur: number): Caustique[] {
  const suivant = tirage(4242);

  return Array.from({ length: NOMBRE_DE_CAUSTIQUES }, (_, i) => ({
    cle: `caustique-${i}`,
    // Elles se resserrent près de la surface : c'est là que la lumière est la plus vive.
    y: hauteur * BANDE_ECLAIREE * Math.pow(suivant(), 1.6),
    opacite: 0.26 - i * 0.035,
    amplitude: 26 + suivant() * 34,
    epaisseur: 2 + suivant() * 2.5,
  }));
}

interface Perle { cle: string; x: number; y: number; taille: number }

/** Les cercles qui composent une nappe : posés sur une double sinusoïde, jamais alignés. */
function semerLesPerles(largeur: number): Perle[] {
  const perles: Perle[] = [];
  const pas = 26;

  for (let x = -largeur, i = 0; x <= largeur * 2; x += pas, i++) {
    perles.push({
      cle: `perle-${i}`,
      x,
      y: Math.sin(x / 54) * 10 + Math.sin(x / 21) * 4.5,
      taille: 0.8 + Math.abs(Math.sin(x / 37)) * 0.7,
    });
  }

  return perles;
}

function semerLesBulles(largeur: number): Bulle[] {
  const suivant = tirage(1337);

  return Array.from({ length: NOMBRE_DE_BULLES }, (_, i) => ({
    cle: `bulle-${i}`,
    x: suivant() * largeur,
    // Biaisé vers le petit : une répartition uniforme donne des ballons, pas des bulles.
    r: 1.5 + Math.pow(suivant(), 2.4) * 5,
    depart: suivant(),
  }));
}

function semerLesAuras(largeur: number, hauteur: number): Aura[] {
  return [
    { cle: 'aura-bleue', x: largeur * 0.14, y: hauteur * 0.1, r: hauteur * 0.44, couleur: 'rgba(120, 160, 255, 0.13)', course: largeur * 0.09 },
    { cle: 'aura-ambre', x: largeur * 0.9, y: hauteur * 0.16, r: hauteur * 0.4, couleur: 'rgba(255, 182, 72, 0.11)', course: largeur * 0.07 },
    { cle: 'aura-menthe', x: largeur * 0.62, y: hauteur * 0.92, r: hauteur * 0.48, couleur: 'rgba(47, 217, 197, 0.10)', course: largeur * 0.11 },
    { cle: 'aura-ardoise', x: largeur * 0.06, y: hauteur * 0.78, r: hauteur * 0.38, couleur: 'rgba(91, 127, 166, 0.10)', course: largeur * 0.08 },
  ];
}
