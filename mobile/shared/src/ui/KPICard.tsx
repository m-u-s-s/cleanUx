import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography, shadows, useThemeColors } from '@/theme';
import { Skeleton } from './Skeleton';

interface KPICardProps {
  title: string;
  value: string | number;
  hint?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger';
  loading?: boolean;
}

export function KPICard({ title, value, hint, tone = 'neutral', loading }: KPICardProps) {
  const themeColors = useThemeColors();

  if (loading) {
    return (
      <View style={[styles.container, { backgroundColor: themeColors.card }]}>
        <Skeleton width={60} height={12} />
        <View style={{ height: 8 }} />
        <Skeleton width={80} height={24} />
        {hint && <><View style={{ height: 6 }} /><Skeleton width={50} height={10} /></>}
      </View>
    );
  }

  const toneColor = tone === 'success' ? colors.success[500]
    : tone === 'warning' ? colors.warning[500]
    : tone === 'danger' ? colors.danger[500]
    : colors.brand[500];

  return (
    <View
      style={[styles.container, { backgroundColor: themeColors.card }]}
      accessible
      accessibilityRole="text"
      accessibilityLabel={`${title}: ${value}${hint ? `, ${hint}` : ''}`}
    >
      <Text style={styles.title}>{title}</Text>
      <Text style={[styles.value, { color: toneColor }]}>{value}</Text>
      {hint ? <Text style={styles.hint}>{hint}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#ffffff', borderRadius: radius.lg, padding: spacing.md + 4,
    ...shadows.sm, flex: 1,
  },
  title: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginBottom: spacing.xs },
  value: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    fontVariant: ['tabular-nums'],
  },
  hint: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginTop: spacing.xs },
});
