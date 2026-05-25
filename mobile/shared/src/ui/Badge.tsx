import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';

type Variant = 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'info';

interface BadgeProps {
  label: string;
  variant?: Variant;
}

const variantColors: Record<Variant, { bg: string; text: string }> = {
  neutral: { bg: colors.surface[200], text: colors.surface[700] },
  brand:   { bg: colors.brand[100],   text: colors.brand[700] },
  success: { bg: colors.success[50],  text: colors.success[700] },
  warning: { bg: colors.warning[50],  text: colors.warning[700] },
  danger:  { bg: colors.danger[50],   text: colors.danger[700] },
  info:    { bg: colors.brand[50],    text: colors.brand[600] },
};

export function Badge({ label, variant = 'neutral' }: BadgeProps) {
  const v = variantColors[variant];
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
