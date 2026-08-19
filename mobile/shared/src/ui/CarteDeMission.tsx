import React from 'react';
import { View, Text, StyleSheet, type StyleProp, type ViewStyle } from 'react-native';
import { GlassSurface } from './GlassSurface';
import { spacing, typography, radius, colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * Ce que la carte demande à celui qui la lit.
 *
 * Trois tons, pas davantage. Un quatrième obligerait à choisir, et un choix qui se discute finit
 * par se prendre au hasard : les cartes cesseraient alors de vouloir dire quelque chose.
 */
export type TonDeMission = 'attention' | 'decision' | 'neutre';

interface CarteDeMissionProps {
  ton?: TonDeMission;
  titre?: string;
  /** Une ligne sous le titre : ce que la carte engage, jamais un paragraphe. */
  chapeau?: string;
  children?: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  testID?: string;
}

/**
 * LA GRAMMAIRE COMMUNE DES CARTES DE MISSION.
 *
 * ── LE DÉFAUT QU'ELLE CORRIGE ────────────────────────────────────────────────────────────────
 *
 * Chaque carte du parcours mission — retard, to-do, nouveau devis — s'était fabriqué son propre
 * fond : un aplat pastel, posé à plat, avec ses marges à elle. Sur l'application prestataire,
 * dont tout le reste est en verre sur fond nuit, ces aplats se lisaient comme des morceaux
 * rapportés d'une autre application. Un écran qu'on reconnaît comme rapporté est un écran raté.
 *
 * ── CE QU'ELLE FAIT, ET CE QU'ELLE NE FAIT PAS ───────────────────────────────────────────────
 *
 * Elle réutilise `GlassSurface`, la plaque du projet : le flou, le voile, l'arête haute plus
 * claire que la basse. Elle n'invente aucune matière.
 *
 * Le SENS ne passe plus par un aplat mais par un RAIL vertical de quatre points, à gauche. Un
 * aplat colore toute la surface et écrase le texte qu'il porte ; un rail se voit du coin de l'œil,
 * ne coûte aucun contraste, et survit au mode sombre sans se repeindre. C'est la retenue qui fait
 * le luxe, pas l'accumulation.
 *
 * Et elle ne bouge pas. Une carte qui apparaît en glissant attire l'œil une fois, puis agace à
 * chaque rendu — le mouvement de ce parcours appartient à la carte et au minuteur, pas au cadre.
 */
export function CarteDeMission({
  ton = 'neutre',
  titre,
  chapeau,
  children,
  style,
  testID,
}: CarteDeMissionProps) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const rail = {
    attention: colors.warning[500],
    decision: colors.brand[500],
    neutre: t.border,
  }[ton];

  return (
    <GlassSurface radius={radius.lg} style={[styles.plaque, style]} testID={testID ?? 'carte-mission'}>
      <View style={[styles.rail, { backgroundColor: rail }]} />

      <View style={styles.contenu}>
        {titre ? <Text style={styles.titre}>{titre}</Text> : null}
        {chapeau ? <Text style={styles.chapeau}>{chapeau}</Text> : null}
        {children}
      </View>
    </GlassSurface>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  plaque: {
    marginTop: spacing.lg,
    flexDirection: 'row',
    overflow: 'hidden',
  },
  rail: { width: 4 },
  contenu: { flex: 1, gap: spacing.sm, padding: spacing.md },
  titre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.textOnGlass,
  },
  chapeau: {
    fontSize: typography.fontSize.xs,
    lineHeight: 17,
    color: t.mutedOnGlass,
  },
});
