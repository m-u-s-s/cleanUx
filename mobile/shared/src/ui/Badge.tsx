import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

type Variant = 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'info';

interface BadgeProps {
  label: string;
  variant?: Variant;
}

/*
   LES EXTREMITES CLAIRES DES RAMPES SONT DES NEUTRES DEGUISES.

   Ce composant portait `colors.brand[100]`, `colors.success[50]` et consorts : des voiles concus
   pour un fond blanc. Sur la nuit, chaque pastille devenait une tache claire — et le module de
   theme dit deja pourquoi, a l'endroit ou il declare `tint` : « Elles remplacent les extremites
   claires des rampes, qui sont des neutres deguises. » Badge n'avait jamais migre.

   Le NEUTRE n'a pas de teinte semantique : il garde son gris en clair, et prend un voile clair
   tres faible sur la nuit — un gris fonce y disparaitrait dans le panneau.
*/
const teintes = (t: ThemeTokens): Record<Variant, { bg: string; text: string }> => ({
  neutral: {
    bg: t.isDark ? 'rgba(232, 238, 252, 0.10)' : colors.surface[200],
    text: t.isDark ? t.textSecondary : colors.surface[700],
  },
  brand:   { bg: t.tint.brand,   text: t.brandText },
  success: { bg: t.tint.success, text: t.success },
  warning: { bg: t.tint.warning, text: t.warning },
  danger:  { bg: t.tint.danger,  text: t.danger },
  info:    { bg: t.tint.brand,   text: t.brandText },
});

export function Badge({ label, variant = 'neutral' }: BadgeProps) {
  const v = teintes(useThemeColors())[variant];
  return (
    <View style={[styles.container, { backgroundColor: v.bg }]}>
      <Text style={[styles.text, { color: v.text }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.pill,
    alignSelf: 'flex-start',
  },
  text: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.medium,
  },
});
