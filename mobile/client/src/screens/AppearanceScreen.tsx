import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Screen, Badge } from '@/ui';
import { useColorScheme } from '@/theme/useColorScheme';
import { colors, spacing, typography, radius } from '@/theme';

const OPTIONS = [
  { mode: 'system' as const, label: 'Automatique', description: 'Suit le réglage du système' },
  { mode: 'light' as const, label: 'Clair', description: 'Toujours en mode clair' },
  { mode: 'dark' as const, label: 'Sombre', description: 'Toujours en mode sombre' },
];

export function AppearanceScreen() {
  const { mode, setMode } = useColorScheme();

  return (
    <Screen>
      <Text style={styles.title}>Apparence</Text>
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

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
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
  rowActive: { backgroundColor: colors.brand[50] },
  rowContent: { flex: 1 },
  label: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
  },
  desc: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginTop: 2 },
});
