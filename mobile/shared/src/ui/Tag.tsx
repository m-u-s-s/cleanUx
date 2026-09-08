import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

type Variant = 'primary' | 'urgent' | 'success' | 'neutral';

interface TagProps {
  label: string;
  variant?: Variant;
}

/** Meme migration que `Badge` : les voiles semantiques a la place des rampes claires. */
const teintes = (t: ThemeTokens): Record<Variant, { bg: string; text: string }> => ({
  primary: { bg: t.tint.brand,   text: t.brandText },
  urgent:  { bg: t.tint.danger,  text: t.danger },
  success: { bg: t.tint.success, text: t.success },
  neutral: {
    bg: t.isDark ? 'rgba(232, 238, 252, 0.08)' : colors.surface[100],
    text: t.isDark ? t.textSecondary : colors.surface[600],
  },
});

export function Tag({ label, variant = 'neutral' }: TagProps) {
  const v = teintes(useThemeColors())[variant];
  return (
    <View style={[styles.container, { backgroundColor: v.bg }]}>
      <Text style={[styles.text, { color: v.text }]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: spacing.sm + 2,
    paddingVertical: spacing.xs,
    borderRadius: radius.sm,
    alignSelf: 'flex-start',
  },
  text: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.medium,
  },
});
