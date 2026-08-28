import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Screen, Badge } from '@/ui';
import { useColorScheme } from '@/theme/useColorScheme';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

const OPTIONS = [
  { mode: 'system' as const, label: 'Automatique', description: 'Suit le réglage du système' },
  { mode: 'light' as const, label: 'Clair', description: 'Toujours en mode clair' },
  { mode: 'dark' as const, label: 'Sombre', description: 'Toujours en mode sombre' },
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
            <Text style={styles.label}>{opt.label}</Text>
            <Text style={styles.desc}>{opt.description}</Text>
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
  // Teinte de marque TRANSLUCIDE, et non `brand[50]` : cet aplat quasi-blanc rendait le texte
  // clair invisible en mode sombre. Un voile se pose sur les deux fonds.
  rowActive: { backgroundColor: 'rgba(99, 102, 241, 0.16)' },
  rowContent: { flex: 1 },
  label: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  desc: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
});
