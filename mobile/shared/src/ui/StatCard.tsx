import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

type Tone = 'neutral' | 'success' | 'warning' | 'danger';

interface StatCardProps {
  title: string;
  value: string | number;
  trend?: 'up' | 'down' | 'flat';
  tone?: Tone;
}

const toneColors: Record<Tone, string> = {
  neutral: colors.surface[600],
  success: colors.success[500],
  warning: colors.warning[500],
  danger: colors.danger[500],
};

const trendArrows: Record<string, string> = { up: '↑', down: '↓', flat: '→' };

export function StatCard({ title, value, trend, tone = 'neutral' }: StatCardProps) {
  const styles = stylesFor(useThemeColors());

  return (
    <View
      style={styles.container}
      accessible
      accessibilityRole="text"
      accessibilityLabel={`${title}: ${value}${trend ? ` tendance ${trend}` : ''}`}
    >
      <Text style={styles.title}>{title}</Text>
      <View style={styles.row}>
        <Text style={[styles.value, { color: toneColors[tone] }]}>{value}</Text>
        {trend ? <Text style={[styles.trend, { color: toneColors[tone] }]}>{trendArrows[trend]}</Text> : null}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: {
    backgroundColor: t.card, borderRadius: radius.md, padding: spacing.md,
    ...shadows.soft,
  },
  title: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginBottom: spacing.xs },
  row: { flexDirection: 'row', alignItems: 'baseline' },
  value: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, fontVariant: ['tabular-nums'] },
  trend: { fontSize: typography.fontSize.sm, marginLeft: spacing.xs, fontWeight: typography.fontWeight.medium },
});
