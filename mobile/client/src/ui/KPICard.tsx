import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography, shadows } from '@/theme';

interface KPICardProps {
  title: string;
  value: string | number;
  hint?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
}

export function KPICard({ title, value, hint, tone = 'neutral' }: KPICardProps) {
  const toneColor = tone === 'success' ? colors.success[500]
    : tone === 'warning' ? colors.warning[500]
    : tone === 'danger' ? colors.danger[500]
    : colors.brand[500];

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{title}</Text>
      <Text style={[styles.value, { color: toneColor }]}>{value}</Text>
      {hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#ffffff', borderRadius: radius.md, padding: spacing.md,
    ...shadows.sm, flex: 1,
  },
  title: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginBottom: spacing.xs },
  value: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold },
  hint: { fontSize: typography.fontSize.xs, color: colors.surface[400], marginTop: spacing.xs },
});
