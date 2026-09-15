import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Button, GlassSurface, Icon } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import { iconeDuMetier } from '@/catalogue';
import type { RepereDeSonde } from '@/catalogue';

export interface FeuilleDuMetierProps {
  repere: RepereDeSonde;
  libellePrix: string;
  onCommander: () => void;
}

/**
 * LA FEUILLE DU MÉTIER CHOISI — fixe, sans geste de glissement.
 *
 * Elle ne montre qu'un prix : le plancher que le moteur de commande utilisera. Un catalogue de
 * « services » avec ses propres prix contredirait le devis.
 */
export function FeuilleDuMetier({ repere, libellePrix, onCommander }: FeuilleDuMetierProps) {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const insets = useSafeAreaInsets();
  const styles = stylesFor(theme);
  const { metier, secteur, rang, total } = repere;

  return (
    <GlassSurface
      strong
      radius={radius.lg}
      style={[styles.feuille, { paddingBottom: spacing.md + insets.bottom }]}
      testID="feuille-du-metier"
    >
      <View style={styles.entete}>
        <View style={styles.pastilleIcone}>
          <Icon name={iconeDuMetier(metier.icon)} size={22} color={theme.action} />
        </View>
        <View style={styles.textes}>
          <Text style={styles.titre} numberOfLines={1} testID="feuille-titre">{metier.name}</Text>
          <Text style={styles.position} numberOfLines={1} testID="feuille-position">
            {tr('catalogue.position', { secteur: secteur.name, rang, total })}
          </Text>
        </View>
      </View>

      {metier.short_description ? (
        <Text style={styles.description} numberOfLines={2}>{metier.short_description}</Text>
      ) : null}

      <Text
        style={[styles.prix, metier.floor_price_cents !== null ? styles.prixConnu : styles.prixInconnu]}
        testID="feuille-prix"
      >
        {libellePrix}
      </Text>

      <Button label={tr('catalogue.commander')} onPress={onCommander} fullWidth size="lg" testID="feuille-commander" />
    </GlassSurface>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  feuille: {
    borderBottomLeftRadius: 0,
    borderBottomRightRadius: 0,
    paddingTop: spacing.md,
    paddingHorizontal: spacing.md,
    gap: spacing.sm,
  },
  entete: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm + spacing.xs },
  pastilleIcone: {
    width: 40, height: 40, borderRadius: radius.md,
    alignItems: 'center', justifyContent: 'center', backgroundColor: t.tint.brand,
  },
  textes: { flex: 1 },
  titre: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: t.textOnGlass },
  position: { fontSize: typography.fontSize.xs, color: t.mutedOnGlass },
  description: { fontSize: typography.fontSize.xs, color: t.mutedOnGlass },
  prix: { fontSize: typography.fontSize.base },
  prixConnu: { color: t.argent, fontWeight: typography.fontWeight.semibold },
  prixInconnu: { color: t.mutedOnGlass },
});
