import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from './Button';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

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
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  return (
    <View style={[styles.container, compact && styles.compactContainer]}>
      <Text style={styles.title}>{tr('error_state.oups')}</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry && <Button label={tr('error_state.reessayer')} onPress={onRetry} variant="secondary" />}
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl, paddingVertical: spacing['3xl'] },
  compactContainer: { flex: 0, paddingHorizontal: spacing.md, paddingVertical: spacing.lg },
  title: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: t.danger, marginBottom: spacing.xs },
  message: { fontSize: typography.fontSize.sm, color: t.textSecondary, textAlign: 'center', marginBottom: spacing.md },
});
