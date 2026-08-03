import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from './Button';
import { Icon } from './Icon';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface EmptyStateProps {
  title: string;
  message: string;
  icon?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function EmptyState({ title, message, icon, actionLabel, onAction }: EmptyStateProps) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.container}>
      {icon && <Icon name={icon as any} size={48} color={colors.surface[300]} />}
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {actionLabel && onAction && <Button label={actionLabel} onPress={onAction} variant="secondary" />}
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl, paddingVertical: spacing['3xl'] },
  title: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: t.text, marginBottom: spacing.xs, marginTop: spacing.sm },
  message: { fontSize: typography.fontSize.sm, color: t.textMuted, textAlign: 'center', marginBottom: spacing.md },
});
