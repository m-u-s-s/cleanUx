import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from './Button';
import { colors, spacing, typography } from '@/theme';

interface ErrorStateProps {
  message?: string;
  onRetry?: () => void;
  /**
   * Inline variant: sits inside a section of a scrolling screen instead of filling it.
   * Needed when only one query of a screen failed and the rest still has something to show.
   */
  compact?: boolean;
}

export function ErrorState({
  message = 'Une erreur est survenue.',
  onRetry,
  compact = false,
}: ErrorStateProps) {
  return (
    <View style={[styles.container, compact && styles.compactContainer]}>
      <Text style={styles.title}>Oups !</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry && <Button label="Réessayer" onPress={onRetry} variant="secondary" />}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl, paddingVertical: spacing['3xl'] },
  compactContainer: { flex: 0, paddingHorizontal: spacing.md, paddingVertical: spacing.lg },
  title: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: colors.danger[500], marginBottom: spacing.xs },
  message: { fontSize: typography.fontSize.sm, color: colors.surface[500], textAlign: 'center', marginBottom: spacing.md },
});
