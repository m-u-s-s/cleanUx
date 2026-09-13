import React, { useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { BlurMask, Canvas, Circle, LinearGradient, Rect, vec } from '@shopify/react-native-skia';
import { Easing, useDerivedValue, useSharedValue, withTiming } from 'react-native-reanimated';

/*
 * LE CONTRAT DE LA BARRE, DECRIT ICI.
 *
 * `@react-navigation/bottom-tabs` est installe dans `client` et `provider`, PAS dans
 * `shared` : importer son type ici casse `tsc` des deux cotes. Le decrire structurellement
 * suffit — React Navigation passe un objet, il n'exige pas sa propre classe.
 */
interface OngletRoute {
  key: string;
  name: string;
}

interface OngletIconeProps {
  focused: boolean;
  color: string;
  size: number;
}

interface OngletOptions {
  title?: string;
  tabBarLabel?: unknown;
  tabBarIcon?: (props: OngletIconeProps) => React.ReactNode;
  [autre: string]: unknown;
}

export interface BottomTabBarProps {
  state: { index: number; routes: readonly OngletRoute[] };
  descriptors: Record<string, { options: OngletOptions; [autre: string]: unknown }>;
  navigation: {
    emit: (event: { type: 'tabPress'; target: string; canPreventDefault: true }) => { defaultPrevented: boolean };
    navigate: (...args: never[]) => void;
  };
}
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useReducedMotion } from './a11y';

/**
 * LA BARRE « REMONTÉE ».
 *
 * PAS DE PLAQUE, PAS D'ARÊTE. La matière monte du bas en dégradé, comme l'abysse qui remonte :
 * aucune ligne horizontale ne vient couper le rendu de fond. C'est la seule des six propositions
 * qui pose sa garde de lisibilité sans dessiner de bord.
 *
 * LE DÉGRADÉ EST UNE GARDE, PAS UNE DÉCORATION. Le fond de l'application est une image qui va du
 * noir de la quille au blanc des caustiques : sous les libellés, il faut atteindre l'opacité du
 * verre du thème, sinon un onglet devient illisible dès que l'iceberg défile derrière. D'où les
 * arrêts : transparent en haut, `glass` à mi-hauteur, presque plein sous le texte.
 *
 * LA LUEUR SOURD DU BAS. L'onglet actif est marqué par un halo diffus qui monte du bord inférieur,
 * écho du puits de lumière de la scène nuit. Il GLISSE d'un onglet à l'autre — et ne pulse jamais :
 * un point lumineux qui clignote sous le pouce fatigue en deux minutes.
 *
 * PLUS DE DISQUE CENTRAL. L'accueil redevient un onglet parmi cinq, ce qui rend l'ambre à l'argent :
 * elle était la seule couleur chaude du thème, et un bouton de navigation la portait.
 */
export interface BarreOngletsOptions {
  /**
   * Conservé pour les appelants — la barre n'a plus de place centrale.
   *
   * Le retirer d'un coup obligerait à toucher les deux navigateurs dans le même commit que le
   * dessin ; le laisser inerte les découple. Il ne fait plus rien, et c'est écrit.
   */
  routeCentrale?: string;
}

/**
 * La hauteur au-dessus des icônes où le dégradé est encore translucide.
 *
 * ELLE SE DÉDUIT DE L'ARRÊT DU MILIEU, elle ne se choisit pas à l'œil. Le dégradé atteint la
 * densité du verre à 24 % de la hauteur de la barre : il faut donc que la première icône tombe
 * plus bas que ça, quel que soit l'encart du bas. À 26 px elle tombait pile dessus, et le libellé
 * de sourdine rendait 4,20 en clair — mesuré sur l'appareil, pas déduit.
 */
const REMONTEE = 34;

export function creerBarreOnglets(_options: BarreOngletsOptions = {}) {
  return function BarreOnglets(props: BottomTabBarProps) {
    return <Barre {...props} />;
  };
}

function Barre({ state, descriptors, navigation }: BottomTabBarProps) {
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const mouvementReduit = useReducedMotion();
  const styles = feuille(theme);

  /*
   * LA TAILLE EST MESURÉE, PAS SUPPOSÉE. La lueur doit tomber au centre de l'onglet actif ; une
   * largeur d'écran devinée la décale sur chaque appareil, et le décalage ne se voit qu'en main.
   */
  const [taille, setTaille] = useState({ largeur: 0, hauteur: 0 });

  const nombre = state.routes.length;
  const pas = nombre > 0 ? taille.largeur / nombre : 0;
  const cible = pas * (state.index + 0.5);

  const x = useSharedValue(cible);

  useEffect(() => {
    if (mouvementReduit || x.value === 0) {
      x.value = cible;

      return;
    }

    x.value = withTiming(cible, { duration: 260, easing: Easing.out(Easing.cubic) });
  }, [cible, mouvementReduit, x]);

  const centreDeLaLueur = useDerivedValue(() => x.value);

  const presser = (index: number, nomRoute: string, estActif: boolean) => {
    const cibleRoute = state.routes[index];

    if (!cibleRoute) {
      return;
    }

    const evenement = navigation.emit({ type: 'tabPress', target: cibleRoute.key, canPreventDefault: true });

    if (!estActif && !evenement.defaultPrevented) {
      navigation.navigate(nomRoute as never);
    }
  };

  const rendreOnglet = (route: OngletRoute, index: number) => {
    const options = descriptors[route.key]?.options ?? {};
    const estActif = state.index === index;
    const teinte = estActif ? theme.action : theme.textMuted;
    const libelle =
      typeof options.tabBarLabel === 'string' ? options.tabBarLabel : (options.title ?? route.name);

    return (
      <Pressable
        key={route.key}
        accessibilityRole="tab"
        accessibilityState={{ selected: estActif }}
        accessibilityLabel={libelle}
        onPress={() => presser(index, route.name, estActif)}
        style={styles.onglet}
        android_ripple={{ color: theme.glassBorder, borderless: true, radius: 30 }}
      >
        {options.tabBarIcon?.({ focused: estActif, color: teinte, size: 22 })}
        <Text numberOfLines={1} style={[styles.libelle, { color: teinte }]}>
          {libelle}
        </Text>
      </Pressable>
    );
  };

  const basDeSecurite = Math.max(insets.bottom, 10);

  return (
    <View
      testID="barre-onglets"
      style={[styles.socle, { paddingBottom: basDeSecurite }]}
      onLayout={e =>
        setTaille({ largeur: e.nativeEvent.layout.width, hauteur: e.nativeEvent.layout.height })
      }
    >
      {taille.hauteur > 0 ? (
        <Canvas style={StyleSheet.absoluteFill} pointerEvents="none">
          {/*
            LA LUEUR EST DESSOUS, LE DÉGRADÉ PAR-DESSUS. Posée au-dessus, elle éclaircirait la
            surface qui porte le libellé actif et lui mangerait son contraste. Dessous, le dégradé
            la tamise exactement là où le texte se pose, et la laisse respirer plus haut.
          */}
          <Circle
            cx={centreDeLaLueur}
            cy={taille.hauteur}
            r={taille.hauteur * 0.72}
            color={theme.action}
            opacity={0.85}
          >
            <BlurMask blur={taille.hauteur * 0.4} style="normal" />
          </Circle>

          <Rect x={0} y={0} width={taille.largeur} height={taille.hauteur}>
            <LinearGradient
              start={vec(0, 0)}
              end={vec(0, taille.hauteur)}
              colors={[theme.remonteeHaut, theme.remonteeMilieu, theme.remonteeBas]}
              positions={[0, 0.24, 1]}
            />
          </Rect>
        </Canvas>
      ) : null}

      <View style={styles.rangee}>{state.routes.map(rendreOnglet)}</View>
    </View>
  );
}

const feuille = (theme: ThemeTokens) =>
  StyleSheet.create({
    socle: {
      paddingTop: REMONTEE,
    },
    rangee: {
      flexDirection: 'row',
      alignItems: 'flex-end',
    },
    onglet: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
      gap: 3,
      paddingVertical: 6,
      minHeight: 48,
    },
    libelle: {
      fontSize: 10,
      fontWeight: '600',
      letterSpacing: 0.1,
    },
  });
