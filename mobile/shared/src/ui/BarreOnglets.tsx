import React from 'react';
import { Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

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
import { GlassSurface } from './GlassSurface';

/**
 * LA BARRE D'ONGLETS À BOUTON CENTRAL.
 *
 * « Mettre la page d'accueil en évidence dans la nav, au milieu, avec un beau décor. »
 *
 * L'accueil quitte la file et devient un disque surélevé au centre, posé à cheval sur la
 * barre. Les autres onglets se répartissent autour de lui.
 *
 * POURQUOI UNE BARRE ENTIÈREMENT DESSINÉE plutôt que des options passées au navigateur :
 * un onglet surélevé déborde de la barre, et `tabBarStyle` ne sait pas laisser déborder
 * son contenu. Il faut rendre la barre soi-même pour que le disque sorte du cadre.
 *
 * L'ENCART DU BAS EST LU, PAS SUPPOSÉ. Sur un appareil à barre gestuelle, une valeur
 * codée en dur pose la barre sous la poignée du système ou laisse un vide sous elle.
 */
export interface BarreOngletsOptions {
  /** Le nom de route qui prend la place centrale. Absent : barre ordinaire. */
  routeCentrale?: string;
}

export function creerBarreOnglets({ routeCentrale }: BarreOngletsOptions = {}) {
  return function BarreOnglets(props: BottomTabBarProps) {
    return <Barre {...props} routeCentrale={routeCentrale} />;
  };
}

function Barre({ state, descriptors, navigation, routeCentrale }: BottomTabBarProps & BarreOngletsOptions) {
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const styles = feuille(theme);

  const indexCentral = routeCentrale
    ? state.routes.findIndex((r: OngletRoute) => r.name === routeCentrale)
    : -1;

  const lateraux = state.routes
    .map((route: OngletRoute, index: number) => ({ route, index }))
    .filter(({ index }: { index: number }) => index !== indexCentral);

  const milieu = Math.ceil(lateraux.length / 2);
  const gauche = lateraux.slice(0, milieu);
  const droite = lateraux.slice(milieu);

  const presser = (index: number, nomRoute: string, estActif: boolean) => {
    const cible = state.routes[index];

    if (!cible) {
      return;
    }

    const evenement = navigation.emit({ type: 'tabPress', target: cible.key, canPreventDefault: true });

    if (!estActif && !evenement.defaultPrevented) {
      navigation.navigate(nomRoute as never);
    }
  };

  const rendreOnglet = ({ route, index }: { route: (typeof state.routes)[number]; index: number }) => {
    const options = descriptors[route.key]?.options ?? {};
    const estActif = state.index === index;
    const teinte = estActif ? theme.accent : theme.textMuted;
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
        android_ripple={{ color: theme.glassBorder, borderless: true, radius: 28 }}
      >
        {options.tabBarIcon?.({ focused: estActif, color: teinte, size: 22 })}
        <Text numberOfLines={1} style={[styles.libelle, { color: teinte }]}>
          {libelle}
        </Text>
        {estActif ? <View style={styles.pastille} /> : null}
      </Pressable>
    );
  };

  const centre = indexCentral >= 0 ? (state.routes[indexCentral] ?? null) : null;
  const centreActif = indexCentral >= 0 && state.index === indexCentral;
  const optionsCentre = centre ? (descriptors[centre.key]?.options ?? null) : null;

  return (
    <View style={[styles.socle, { paddingBottom: Math.max(insets.bottom, 10) }]}>
      {theme.isDark ? <GlassSurface style={StyleSheet.absoluteFill} /> : null}

      <View style={styles.rangee}>
        <View style={styles.cote}>{gauche.map(rendreOnglet)}</View>

        {centre ? (
          <View style={styles.creux}>
            <Pressable
              accessibilityRole="tab"
              accessibilityState={{ selected: centreActif }}
              accessibilityLabel={
                typeof optionsCentre?.tabBarLabel === 'string' ? optionsCentre.tabBarLabel : centre.name
              }
              onPress={() => presser(indexCentral, centre.name, centreActif)}
              style={({ pressed }) => [styles.disque, pressed && styles.disquePresse]}
            >
              {/* Le halo : c'est lui qui fait « le beau décor ». Il ne pulse pas —
                  un point lumineux qui clignote sous le pouce fatigue en deux minutes. */}
              <View style={styles.halo} pointerEvents="none" />
              {optionsCentre?.tabBarIcon?.({ focused: centreActif, color: theme.textOnAccent, size: 26 })}
            </Pressable>
          </View>
        ) : null}

        <View style={styles.cote}>{droite.map(rendreOnglet)}</View>
      </View>
    </View>
  );
}

const feuille = (theme: ThemeTokens) =>
  StyleSheet.create({
    socle: {
      paddingTop: 30,
      backgroundColor: theme.isDark ? 'transparent' : theme.glassStrong,
      borderTopWidth: theme.isDark ? 0 : StyleSheet.hairlineWidth,
      borderTopColor: theme.glassBorder,
    },
    rangee: {
      flexDirection: 'row',
      alignItems: 'flex-end',
      paddingHorizontal: 8,
    },
    cote: {
      flex: 1,
      flexDirection: 'row',
      justifyContent: 'space-evenly',
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
    pastille: {
      position: 'absolute',
      top: 0,
      width: 4,
      height: 4,
      borderRadius: 2,
      backgroundColor: theme.accent,
    },
    /* Le creux réserve la largeur du disque : sans lui, les onglets latéraux
       glissent sous le bouton et deviennent intouchables. */
    creux: {
      width: 76,
      alignItems: 'center',
    },
    disque: {
      position: 'absolute',
      bottom: 6,
      width: 60,
      height: 60,
      borderRadius: 30,
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: theme.accent,
      borderWidth: 4,
      borderColor: theme.isDark ? theme.bg : theme.glassStrong,
      ...Platform.select({
        ios: {
          shadowColor: theme.accent,
          shadowOpacity: 0.45,
          shadowRadius: 14,
          shadowOffset: { width: 0, height: 6 },
        },
        android: { elevation: 10 },
        default: {},
      }),
    },
    disquePresse: {
      transform: [{ scale: 0.94 }],
    },
    halo: {
      position: 'absolute',
      width: 88,
      height: 88,
      borderRadius: 44,
      backgroundColor: theme.accent,
      opacity: 0.16,
    },
  });
