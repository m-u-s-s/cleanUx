import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Screen, Badge } from '@/ui';
import { useColorScheme } from '@/theme/useColorScheme';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/* Des CLES : traduite ici, la table garderait la langue du demarrage. */
const OPTIONS = [
  { mode: 'system' as const, label: 'appearance.automatique_2', description: 'appearance.suit_le_reglage_du_systeme_2' },
  { mode: 'light' as const, label: 'appearance.clair_2', description: 'appearance.toujours_en_mode_clair_2' },
  { mode: 'dark' as const, label: 'appearance.sombre_2', description: 'appearance.toujours_en_mode_sombre_2' },
];

export function AppearanceScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { mode, setMode } = useColorScheme();

  return (
    <Screen>
      <Text style={styles.title}>{tr('appearance.apparence')}</Text>
      {OPTIONS.map(opt => (
        <TouchableOpacity
          key={opt.mode}
          style={[styles.row, mode === opt.mode && styles.rowActive]}
          onPress={() => setMode(opt.mode)}
        >
          <View style={styles.rowContent}>
            <Text style={styles.label}>{tr(opt.label)}</Text>
            <Text style={styles.desc}>{tr(opt.description)}</Text>
          </View>
          {mode === opt.mode && <Badge label="✓" variant="success" />}
        </TouchableOpacity>
      ))}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    marginBottom: spacing.xs,
  },
  // Le VOILE de marque du theme, et non un rgba fige : celui d'avant etait reste indigo, seule
  // trace visible de l'ancienne palette apres deux refontes.
  rowActive: { backgroundColor: t.tint.brand },
  rowContent: { flex: 1 },
  label: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  desc: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
});
