import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from './Button';
import { Icon } from './Icon';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { traduireMaintenant } from '@/i18n';

interface ErrorScreenProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  retryLabel?: string;
}

export function ErrorScreen({
  title = traduireMaintenant('error.une_erreur_est_survenue'),
  message = traduireMaintenant('error.verifiez_votre_connexion_et_reessayez'),
  onRetry,
  retryLabel = traduireMaintenant('commun.reessayer'),
}: ErrorScreenProps) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.container}>
      <Icon name="alert-circle-outline" size={48} color={colors.danger[500]} />
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry && (
        <Button label={retryLabel} onPress={onRetry} variant="secondary" />
      )}
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing['3xl'],
  },
  title: {
    ...typography.preset.headline,
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.xs,
    marginTop: spacing.sm,
    textAlign: 'center',
  },
  message: {
    ...typography.preset.bodyReadable,
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    textAlign: 'center',
    marginBottom: spacing.md,
  },
});
