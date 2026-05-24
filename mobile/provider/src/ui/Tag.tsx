import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius, spacing, typography } from '@/theme';

type Variant = 'primary' | 'urgent' | 'success' | 'neutral';

interface TagProps {
  label: string;
  variant?: Variant;
}

const variantColors: Record<Variant, { bg: string; text: string }> = {
  primary: { bg: colors.brand[100],  text: colors.brand[700] },
  urgent:  { bg: colors.danger[50],  text: colors.danger[700] },
  success: { bg: colors.success[50], text: colors.success[700] },
  neutral: { bg: colors.surface[100], text: colors.surface[600] },
};

export function Tag({ label, variant = 'neutral' }: TagProps) {
  const v = variantColors[variant];
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
